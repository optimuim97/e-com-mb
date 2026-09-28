<?php

namespace Modules\Auth\Services;

use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Support\Shop;

/**
 * Envoi de messages WhatsApp à un client (pas à l'admin).
 * Utilise la WhatsApp Cloud API (mêmes credentials que AdminWhatsAppChannel).
 *
 * ⚠ La règle qui gouverne tout ce service : WhatsApp n'accepte du texte libre
 * que pendant les 24 h qui suivent le dernier message reçu de la cliente. Passé
 * ce délai, seul un modèle approuvé par Meta franchit la porte — un envoi en
 * texte libre revient avec l'erreur 131047, silencieusement pour l'appelant.
 * D'où `sendSessionOrTemplate()` : c'est lui qu'il faut appeler pour tout
 * message déclenché par la boutique, jamais `sendText()` directement.
 */
class CustomerWhatsAppService
{
    /** Endpoint /messages de la Cloud API (version configurable). */
    private function endpoint(string $phoneId): string
    {
        $version = config('services.whatsapp.graph_version', 'v22.0');
        return "https://graph.facebook.com/{$version}/{$phoneId}/messages";
    }

    /**
     * Envoie un message TEMPLATE approuvé (à l'initiative du business).
     *
     * ⚠ À utiliser pour tout message hors fenêtre de 24h (OTP, confirmation
     * de commande…) : l'API WhatsApp REFUSE le texte libre dans ce cas.
     * Le template doit exister et être approuvé dans le WhatsApp Business Account.
     *
     * @param  array<int, string>  $bodyParams  paramètres {{1}}, {{2}}… du corps
     * @return bool  true si accepté par l'API
     */
    public function sendTemplate(string $phone, string $template, string $lang = 'fr', array $bodyParams = []): bool
    {
        $apiToken = config('services.whatsapp.token', '');
        $phoneId  = config('services.whatsapp.phone_number_id', '');

        if (! $apiToken || ! $phoneId) {
            Log::info('[CustomerWhatsApp] WhatsApp non configuré, template non envoyé.');
            return false;
        }

        $to = preg_replace('/\D+/', '', $phone);
        if (! $to) return false;

        $template_payload = [
            'name'     => $template,
            'language' => ['code' => $lang],
        ];
        if ($bodyParams) {
            $template_payload['components'] = [[
                'type'       => 'body',
                'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => (string) $p], $bodyParams),
            ]];
        }

        $response = Http::timeout(10)
            ->withToken($apiToken)
            ->post($this->endpoint($phoneId), [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'template',
                'template'          => $template_payload,
            ]);

        if ($response->failed()) {
            Log::warning('[CustomerWhatsApp] Envoi template échoué', [
                'template' => $template,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * La fenêtre de service de 24 h est-elle ouverte pour ce numéro ?
     *
     * Elle s'ouvre au dernier message reçu de la cliente — ceux que le webhook
     * dépose dans `whatsapp_messages`. Comparaison sur les 8 derniers chiffres :
     * Meta livre `2250700000000` là où la base garde souvent `0700000000`.
     */
    public function sessionIsOpen(string $phone): bool
    {
        $local = substr(preg_replace('/\D+/', '', $phone), -8);

        if (strlen($local) < 8) {
            return false;
        }

        return WhatsAppMessage::where('from_phone', 'like', "%{$local}")
            ->where('received_at', '>=', now()->subDay())
            ->exists();
    }

    /**
     * Envoi d'un message déclenché par la boutique — la porte d'entrée unique.
     *
     * Texte libre tant que la cliente a écrit dans les 24 h (le message est
     * alors complet et lisible), modèle approuvé sinon. Le repli n'est pas une
     * dégradation choisie par confort : hors fenêtre, l'API refuse tout le
     * reste.
     *
     * @param  array<int, string>  $templateParams  paramètres {{1}}, {{2}}… du modèle
     */
    public function sendSessionOrTemplate(
        string $phone,
        string $freeText,
        string $templateKey,
        array $templateParams = [],
    ): bool {
        if ($this->sessionIsOpen($phone)) {
            return $this->sendText($phone, $freeText);
        }

        $template = config("services.whatsapp.templates.{$templateKey}");

        if (! $template) {
            Log::warning('[CustomerWhatsApp] Aucun modèle configuré pour ' . $templateKey . ' — message non envoyé.');

            return false;
        }

        return $this->sendTemplate(
            $phone,
            $template,
            (string) config('services.whatsapp.templates.lang', 'fr'),
            $templateParams,
        );
    }

    /**
     * Code à usage unique.
     *
     * Toujours par modèle : une cliente qui a perdu son mot de passe n'a par
     * définition pas écrit à la boutique dans l'heure, la fenêtre est fermée.
     */
    public function sendOtp(string $phone, string $code, string $label): void
    {
        $apiToken = config('services.whatsapp.token', '');
        $phoneId  = config('services.whatsapp.phone_number_id', '');

        if (! $apiToken || ! $phoneId) {
            // Pas configuré → on log et on échoue proprement.
            Log::warning('[CustomerWhatsApp] WhatsApp non configuré, OTP non envoyé.');
            throw ValidationException::withMessages([
                'channel' => ['Le canal WhatsApp n\'est pas configuré. Utilisez l\'email.'],
            ]);
        }

        $texte = '*' . Shop::name() . "*\n\nVotre code de réinitialisation {$label} : *{$code}*\n\nCe code expire dans 10 minutes.\nNe le partagez avec personne.";

        if (! $this->sendSessionOrTemplate($phone, $texte, 'otp_code', [$code])) {
            throw ValidationException::withMessages([
                'channel' => ['Impossible d\'envoyer le code via WhatsApp. Réessayez ou utilisez l\'email.'],
            ]);
        }
    }

    /**
     * Envoie un DOCUMENT (ex : facture PDF) à un client — non bloquant.
     *
     * La Cloud API exige un upload du média d'abord (endpoint /media),
     * puis l'envoi d'un message type "document" référençant le media_id.
     *
     * @param  string  $binary    contenu binaire du fichier (ex : $pdf->output())
     * @param  string  $filename  nom affiché chez le client (ex : facture-ORD-….pdf)
     * @param  string  $caption   légende optionnelle sous le document
     * @return bool  true si accepté par l'API WhatsApp
     */
    public function sendDocument(string $phone, string $binary, string $filename, string $caption = ''): bool
    {
        $apiToken = config('services.whatsapp.token', '');
        $phoneId  = config('services.whatsapp.phone_number_id', '');

        if (! $apiToken || ! $phoneId) {
            Log::info('[CustomerWhatsApp] WhatsApp non configuré, document non envoyé.');
            return false;
        }

        $to = preg_replace('/\D+/', '', $phone);
        if (! $to) return false;

        $version = config('services.whatsapp.graph_version', 'v22.0');

        // 1) Upload du média → media_id
        $upload = Http::timeout(30)
            ->withToken($apiToken)
            ->attach('file', $binary, $filename, ['Content-Type' => 'application/pdf'])
            ->post("https://graph.facebook.com/{$version}/{$phoneId}/media", [
                'messaging_product' => 'whatsapp',
                'type'              => 'application/pdf',
            ]);

        if ($upload->failed() || empty($upload->json('id'))) {
            Log::warning('[CustomerWhatsApp] Upload document échoué', [
                'status' => $upload->status(),
                'body'   => $upload->body(),
            ]);
            return false;
        }

        // 2) Message document référençant le média uploadé
        $document = [
            'id'       => $upload->json('id'),
            'filename' => $filename,
        ];
        if ($caption !== '') {
            $document['caption'] = $caption;
        }

        $response = Http::timeout(15)
            ->withToken($apiToken)
            ->post($this->endpoint($phoneId), [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'document',
                'document'          => $document,
            ]);

        if ($response->failed()) {
            Log::warning('[CustomerWhatsApp] Envoi document échoué', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Envoi générique d'un message texte à un client (non bloquant).
     * Utilisé pour les confirmations de commande : on ne lève jamais
     * d'exception (l'email reste le canal de secours), on logge seulement.
     *
     * @return bool  true si le message a été accepté par l'API WhatsApp
     */
    public function sendText(string $phone, string $message): bool
    {
        $apiToken = config('services.whatsapp.token', '');
        $phoneId  = config('services.whatsapp.phone_number_id', '');

        if (! $apiToken || ! $phoneId) {
            Log::info('[CustomerWhatsApp] WhatsApp non configuré, message non envoyé.');
            return false;
        }

        $to = preg_replace('/\D+/', '', $phone);
        if (! $to) return false;

        $response = Http::timeout(10)
            ->withToken($apiToken)
            ->post($this->endpoint($phoneId), [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'text',
                'text'              => ['body' => $message],
            ]);

        if ($response->failed()) {
            Log::warning('[CustomerWhatsApp] Envoi message échoué', [
                'status' => $response->status(),
                'body'   => $response->body(),
                'indice' => $this->hint($response->json('error.code')),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Traduit les codes d'erreur Meta qui reviennent le plus souvent.
     *
     * Le corps renvoyé par la Graph API dit « Message failed to send » sans
     * expliquer quoi corriger. Ces trois-là couvrent la quasi-totalité des
     * échecs de mise en route, et les chercher chaque fois coûte une demi-heure.
     */
    private function hint(mixed $code): ?string
    {
        return match ((int) $code) {
            131047  => 'Fenêtre de 24 h fermée : ce message exige un modèle approuvé (voir sendSessionOrTemplate).',
            131026  => 'Numéro destinataire sans compte WhatsApp, ou format incorrect (attendu : 225XXXXXXXXXX).',
            132001  => 'Modèle introuvable ou non approuvé dans ce WhatsApp Business Account, ou mauvaise langue.',
            190     => 'Jeton expiré ou révoqué : régénérer un jeton permanent depuis l\'utilisateur système.',
            default => null,
        };
    }
}
