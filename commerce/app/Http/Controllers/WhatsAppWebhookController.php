<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Point de terminaison Webhook de la WhatsApp Cloud API.
 *
 * Meta procède en deux temps :
 *   1. un GET de vérification à l'enregistrement de l'URL, auquel il faut
 *      renvoyer le « challenge » en texte brut si le token correspond ;
 *   2. des POST pour chaque message entrant et chaque changement de statut.
 *
 * L'URL est publique : la signature de chaque POST est vérifiée avec le
 * secret de l'application, sans quoi n'importe qui pourrait injecter de
 * faux événements.
 */
class WhatsAppWebhookController extends Controller
{
    /**
     * Vérification de l'URL par Meta (GET).
     *
     * Doit répondre en texte brut avec la valeur de hub.challenge, sinon
     * l'enregistrement du webhook est refusé côté tableau de bord.
     */
    public function verify(Request $request): Response
    {
        $expected = (string) config('services.whatsapp.verify_token', '');

        if ($expected === '') {
            Log::warning('[WhatsApp] Vérification reçue mais WHATSAPP_VERIFY_TOKEN n\'est pas défini.');
            return response('Webhook non configuré', 503);
        }

        $mode      = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $token     = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));

        if ($mode === 'subscribe' && hash_equals($expected, $token)) {
            Log::info('[WhatsApp] Webhook vérifié par Meta.');
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('[WhatsApp] Vérification refusée (token invalide).');
        return response('Token de vérification invalide', 403);
    }

    /**
     * Réception des événements (POST).
     *
     * Meta considère toute réponse autre que 2xx comme un échec et réessaie
     * pendant plusieurs jours, en accélérant la cadence. On accuse donc
     * réception immédiatement et on ne laisse jamais une exception de
     * traitement transformer un événement en boucle de renvois.
     */
    public function handle(Request $request): Response
    {
        if (! $this->signatureIsValid($request)) {
            Log::warning('[WhatsApp] Signature invalide — événement ignoré.');
            return response()->json(['status' => 'invalid signature'], 403);
        }

        try {
            $this->dispatchEvents($request->json()->all());
        } catch (\Throwable $e) {
            // On journalise sans propager : renvoyer une erreur ferait
            // rejouer l'événement en boucle par Meta.
            Log::error('[WhatsApp] Traitement du webhook en échec : ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Contrôle de l'en-tête X-Hub-Signature-256 (HMAC SHA-256 du corps brut).
     *
     * Sans secret configuré, on refuse : une URL publique acceptant du JSON
     * non signé permettrait d'injecter de faux messages.
     */
    private function signatureIsValid(Request $request): bool
    {
        $secret = (string) config('services.whatsapp.app_secret', '');

        if ($secret === '') {
            Log::warning('[WhatsApp] WHATSAPP_APP_SECRET absent — impossible de valider la signature.');
            return false;
        }

        $header = (string) $request->header('X-Hub-Signature-256', '');
        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $header);
    }

    /**
     * Parcourt la charge utile : les messages entrants sont enregistrés dans
     * la file de traitement, les statuts d'envoi restent au journal.
     *
     * La structure Meta est imbriquée : entry[] → changes[] → value, où
     * « value » porte soit des messages entrants, soit des statuts de
     * messages envoyés (remis, lu, en échec).
     */
    private function dispatchEvents(array $payload): void
    {
        // La charge utile vient d'un tiers : chaque niveau est vérifié
        // plutôt que supposé, sinon une structure inattendue déclencherait
        // un avertissement PHP au milieu du traitement.
        foreach ($this->arrayAt($payload, 'entry') as $entry) {
            foreach ($this->arrayAt($entry, 'changes') as $change) {
                $value = is_array($change['value'] ?? null) ? $change['value'] : [];

                // Meta livre le nom du profil à part, indexé par numéro.
                $noms = $this->contactNames($value);

                foreach ($this->arrayAt($value, 'messages') as $message) {
                    if (! is_array($message)) continue;
                    $this->storeInboundMessage($message, $noms);
                }

                foreach ($this->arrayAt($value, 'statuses') as $status) {
                    if (! is_array($status)) continue;
                    Log::info('[WhatsApp] Statut de message', [
                        'id'           => $status['id'] ?? null,
                        'statut'       => $status['status'] ?? null,
                        'destinataire' => $status['recipient_id'] ?? null,
                        'erreur'       => $status['errors'][0]['title'] ?? null,
                    ]);
                }
            }
        }
    }

    /**
     * Enregistre un message entrant dans la file à traiter.
     *
     * `firstOrCreate` sur l'identifiant Meta : un même événement peut arriver
     * plusieurs fois (renvoi après timeout), et un doublon en file ferait
     * saisir deux fois la même commande — soit précisément ce que cette file
     * est censée empêcher.
     *
     * @param  array<string, mixed>  $message
     * @param  array<string, string> $noms  Numéro → nom du profil
     */
    private function storeInboundMessage(array $message, array $noms): void
    {
        $waId = (string) ($message['id'] ?? '');
        if ($waId === '') {
            Log::warning('[WhatsApp] Message sans identifiant — non enregistré.', ['message' => $message]);

            return;
        }

        $from = (string) ($message['from'] ?? '');

        WhatsAppMessage::firstOrCreate(
            ['wa_message_id' => $waId],
            [
                'from_phone'   => $from,
                'contact_name' => $noms[$from] ?? null,
                'type'         => (string) ($message['type'] ?? 'text'),
                'body'         => $this->extractBody($message),
                'status'       => WhatsAppMessage::STATUS_PENDING,
                // `timestamp` Meta est en secondes epoch ; à défaut, l'heure
                // de réception vaut mieux qu'une date nulle en tête de file.
                'received_at'  => isset($message['timestamp'])
                    ? now()->setTimestamp((int) $message['timestamp'])
                    : now(),
                'payload'      => $message,
            ]
        );
    }

    /**
     * Texte exploitable du message, quel que soit son type.
     *
     * Une photo de produit légendée « je veux celui-ci en 50 ml » porte la
     * commande dans sa légende : l'ignorer reviendrait à perdre le message.
     *
     * @param  array<string, mixed>  $message
     */
    private function extractBody(array $message): ?string
    {
        return $message['text']['body']
            ?? $message['image']['caption']
            ?? $message['video']['caption']
            ?? $message['document']['caption']
            ?? $message['button']['text']
            ?? $message['interactive']['list_reply']['title']
            ?? $message['interactive']['button_reply']['title']
            ?? null;
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, string>
     */
    private function contactNames(array $value): array
    {
        $noms = [];

        foreach ($this->arrayAt($value, 'contacts') as $contact) {
            if (! is_array($contact)) continue;
            $waId = (string) ($contact['wa_id'] ?? '');
            $nom  = $contact['profile']['name'] ?? null;

            if ($waId !== '' && $nom) {
                $noms[$waId] = (string) $nom;
            }
        }

        return $noms;
    }

    /** Renvoie $source[$cle] si c'est bien un tableau, sinon un tableau vide. */
    private function arrayAt(array $source, string $cle): array
    {
        return is_array($source[$cle] ?? null) ? $source[$cle] : [];
    }
}
