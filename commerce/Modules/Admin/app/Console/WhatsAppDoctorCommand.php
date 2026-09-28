<?php

namespace Modules\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\Admin\Models\Setting;
use App\Support\Shop;

/**
 * Vérifie la chaîne WhatsApp de bout en bout, contre l'API Meta.
 *
 * Mettre la Cloud API en route consiste à réunir cinq valeurs éparpillées dans
 * trois tableaux de bord différents, et l'API ne dit jamais laquelle manque :
 * elle répond « (#100) Invalid parameter » pour un jeton révoqué comme pour un
 * identifiant de numéro pris à la place de celui du compte. Cette commande pose
 * les questions dans l'ordre où elles se cassent et nomme le coupable.
 *
 * Usage :
 *   php artisan shop:whatsapp-doctor
 *   php artisan shop:whatsapp-doctor --to=2250700000000   (envoi réel de test)
 */
class WhatsAppDoctorCommand extends Command
{
    protected $signature = 'shop:whatsapp-doctor
        {--to= : Numéro à qui envoyer un message de test (format 225XXXXXXXXXX)}';

    protected $description = 'Diagnostique la configuration WhatsApp Cloud API contre l\'API Meta';

    private array $lignes = [];
    private bool  $bloquant = false;

    public function handle(): int
    {
        $token   = (string) config('services.whatsapp.token', '');
        $phoneId = (string) config('services.whatsapp.phone_number_id', '');
        $wabaId  = (string) config('services.whatsapp.waba_id', '');
        $version = (string) config('services.whatsapp.graph_version', 'v22.0');

        $this->newLine();
        $this->info('🩺 Diagnostic WhatsApp — ' . Shop::name());
        $this->newLine();

        // ── 1. Ce qui est présent dans .env ──────────────────────────────────
        $this->titre('1. Variables d\'environnement');
        $this->check('WHATSAPP_API_TOKEN', $token !== '', $this->masque($token), 'Absent — aucun envoi possible.');
        $this->check('WHATSAPP_PHONE_NUMBER_ID', $phoneId !== '', $phoneId, 'Absent — aucun envoi possible.');
        $this->check('WHATSAPP_WABA_ID', $wabaId !== '', $wabaId, 'Absent — les modèles ne peuvent pas être vérifiés.', bloquant: false);
        $this->check('WHATSAPP_VERIFY_TOKEN', (string) config('services.whatsapp.verify_token', '') !== '', '(défini)', 'Absent — Meta refusera d\'enregistrer le webhook.', bloquant: false);
        $this->check('WHATSAPP_APP_SECRET', (string) config('services.whatsapp.app_secret', '') !== '', '(défini)', 'Absent — tous les messages entrants seront rejetés.', bloquant: false);
        $this->vider();

        if ($token === '' || $phoneId === '') {
            $this->error('Jeton ou identifiant de numéro manquant : le reste du diagnostic est sans objet.');

            return self::FAILURE;
        }

        // ── 2. Le jeton est-il vivant, et jusqu'à quand ? ────────────────────
        $this->titre('2. Jeton d\'accès');
        $debug = Http::timeout(15)->get("https://graph.facebook.com/{$version}/debug_token", [
            'input_token'  => $token,
            'access_token' => $token,
        ]);

        if ($debug->failed()) {
            $this->echec('Jeton refusé par Meta', $debug->json('error.message') ?? $debug->body());
            $this->bloquant = true;
        } else {
            $data    = $debug->json('data', []);
            $expire  = $data['expires_at'] ?? null;
            $type    = $data['type'] ?? '—';
            $valide  = (bool) ($data['is_valid'] ?? false);

            $this->check('Jeton valide', $valide, "type : {$type}", 'Jeton invalide ou révoqué.');

            // expires_at à 0 = jeton permanent (utilisateur système). Tout le
            // reste est un jeton temporaire qui mourra sans prévenir — c'est
            // l'erreur de mise en route la plus fréquente et la plus coûteuse,
            // parce qu'elle se manifeste des jours après, en production.
            if ($expire === 0 || $expire === null) {
                $this->ok('Jeton permanent', 'aucune expiration (utilisateur système)');
            } else {
                $this->echec(
                    'Jeton TEMPORAIRE',
                    'expire le ' . date('d/m/Y H:i', (int) $expire)
                    . ' — remplacer par un jeton d\'utilisateur système, sinon tout s\'arrête à cette date.'
                );
            }

            $scopes = $data['scopes'] ?? [];
            foreach (['whatsapp_business_messaging', 'whatsapp_business_management'] as $requis) {
                $this->check("Permission {$requis}", in_array($requis, $scopes, true), 'accordée', 'Manquante sur le jeton.');
            }
        }
        $this->vider();

        // ── 3. Le numéro ─────────────────────────────────────────────────────
        $this->titre('3. Numéro expéditeur');
        $numero = Http::timeout(15)->withToken($token)
            ->get("https://graph.facebook.com/{$version}/{$phoneId}", [
                'fields' => 'display_phone_number,verified_name,quality_rating,code_verification_status,platform_type',
            ]);

        if ($numero->failed()) {
            $this->echec(
                'Numéro inaccessible',
                ($numero->json('error.message') ?? $numero->body())
                . ' — vérifier que WHATSAPP_PHONE_NUMBER_ID est bien l\'identifiant du NUMÉRO et non celui du compte.'
            );
            $this->bloquant = true;
        } else {
            $this->ok('Numéro', $numero->json('display_phone_number') ?? '—');
            $this->ok('Nom affiché', $numero->json('verified_name') ?? '—');
            $this->ok('Qualité', $numero->json('quality_rating') ?? '—');

            $verif = $numero->json('code_verification_status');
            $this->check('Numéro vérifié', $verif === 'VERIFIED', (string) $verif, "État : {$verif} — le numéro doit être vérifié pour émettre.");

            // Un numéro resté sur l'application WhatsApp Business ne peut pas
            // recevoir d'appels API : c'est l'exclusivité app / Cloud API.
            $plateforme = $numero->json('platform_type');
            if ($plateforme && $plateforme !== 'CLOUD_API') {
                $this->echec('Plateforme', "{$plateforme} — ce numéro n'est pas sur la Cloud API.");
            } elseif ($plateforme) {
                $this->ok('Plateforme', $plateforme);
            }
        }
        $this->vider();

        // ── 4. Les modèles approuvés ─────────────────────────────────────────
        $this->titre('4. Modèles de message');
        if ($wabaId === '') {
            $this->avertir('WHATSAPP_WABA_ID absent — impossible de lister les modèles.');
        } else {
            $modeles = Http::timeout(15)->withToken($token)
                ->get("https://graph.facebook.com/{$version}/{$wabaId}/message_templates", [
                    'fields' => 'name,status,language,category',
                    'limit'  => 100,
                ]);

            if ($modeles->failed()) {
                $this->echec('Modèles inaccessibles', $modeles->json('error.message') ?? $modeles->body());
            } else {
                $approuves = collect($modeles->json('data', []))
                    ->filter(fn ($m) => ($m['status'] ?? '') === 'APPROVED')
                    ->pluck('language', 'name');

                $langue = (string) config('services.whatsapp.templates.lang', 'fr');

                foreach (['order_confirmation', 'admin_new_order', 'otp_code'] as $cle) {
                    $nom = (string) config("services.whatsapp.templates.{$cle}");
                    $this->check(
                        "Modèle « {$nom} »",
                        $approuves->has($nom),
                        'approuvé (' . $approuves->get($nom) . ')',
                        "Absent ou non approuvé — les envois hors fenêtre de 24 h échoueront pour : {$cle}."
                    );
                }

                $this->ligne('', count($approuves) . ' modèle(s) approuvé(s) au total, langue attendue : ' . $langue);
            }
        }
        $this->vider();

        // ── 5. Le webhook ────────────────────────────────────────────────────
        $this->titre('5. Webhook');
        $url = rtrim((string) config('app.url'), '/') . '/api/whatsapp/webhook';
        $this->ligne('URL à déclarer', $url);
        $this->ligne('Champ à cocher', 'messages');

        if ($wabaId !== '') {
            $abonnement = Http::timeout(15)->withToken($token)
                ->get("https://graph.facebook.com/{$version}/{$wabaId}/subscribed_apps");

            $abonnes = collect($abonnement->json('data', []))->count();
            $this->check(
                'Application abonnée au compte',
                $abonnement->successful() && $abonnes > 0,
                "{$abonnes} application(s)",
                'Aucune application abonnée — les messages entrants n\'arriveront jamais.'
            );
        }

        $numeroAdmin = (string) Setting::get('whatsapp_admin_number', '');
        $this->check('Numéro d\'alerte de la gérante', $numeroAdmin !== '', $numeroAdmin, 'Non renseigné dans Paramètres — aucune alerte de commande.', bloquant: false);
        $this->vider();

        // ── 6. Envoi réel ────────────────────────────────────────────────────
        if ($destinataire = $this->option('to')) {
            $this->titre('6. Envoi de test');
            $this->envoiDeTest($token, $phoneId, $version, $destinataire);
            $this->vider();
        } else {
            $this->line('  Ajouter <options=bold>--to=225XXXXXXXXXX</> pour tenter un envoi réel.');
            $this->newLine();
        }

        if ($this->bloquant) {
            $this->error('❌ Configuration incomplète — voir les lignes marquées ci-dessus.');

            return self::FAILURE;
        }

        $this->info('✓ Chaîne WhatsApp opérationnelle.');

        return self::SUCCESS;
    }

    /**
     * Tente un envoi réel et interprète le refus éventuel.
     *
     * Le texte libre ne passe que si le destinataire a écrit à la boutique dans
     * les 24 h. Un échec 131047 ici n'est donc pas une panne : c'est la preuve
     * que la fenêtre est fermée, et que les modèles sont bien indispensables.
     */
    private function envoiDeTest(string $token, string $phoneId, string $version, string $destinataire): void
    {
        $to = preg_replace('/\D+/', '', $destinataire);

        $reponse = Http::timeout(15)->withToken($token)
            ->post("https://graph.facebook.com/{$version}/{$phoneId}/messages", [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'text',
                'text'              => ['body' => 'Test ' . Shop::name() . ' — ' . now()->format('d/m/Y H:i')],
            ]);

        if ($reponse->successful()) {
            $this->ok('Message envoyé', "à {$to} — fenêtre de 24 h ouverte");

            return;
        }

        $code = (int) $reponse->json('error.code');

        if ($code === 131047) {
            $this->avertir(
                'Texte libre refusé (131047) : la fenêtre de 24 h est fermée pour ce numéro. '
                . 'C\'est le comportement normal — le site passe alors par un modèle approuvé.'
            );

            return;
        }

        $this->echec('Envoi refusé (' . $code . ')', $reponse->json('error.message') ?? $reponse->body());
    }

    // ── Restitution ───────────────────────────────────────────────────────────

    private function titre(string $texte): void
    {
        $this->line("  <options=bold>{$texte}</>");
    }

    private function check(string $libelle, bool $ok, string $valeur, string $probleme, bool $bloquant = true): void
    {
        if ($ok) {
            $this->ok($libelle, $valeur);

            return;
        }

        if ($bloquant) {
            $this->echec($libelle, $probleme);
        } else {
            $this->avertir("{$libelle} — {$probleme}");
        }
    }

    private function ok(string $libelle, string $valeur): void
    {
        $this->lignes[] = ['<fg=green>✓</>', $libelle, $valeur];
    }

    private function echec(string $libelle, string $detail): void
    {
        $this->bloquant = true;
        $this->lignes[] = ['<fg=red>✗</>', $libelle, $detail];
    }

    private function avertir(string $texte): void
    {
        $this->lignes[] = ['<fg=yellow>!</>', '', $texte];
    }

    private function ligne(string $libelle, string $valeur): void
    {
        $this->lignes[] = [' ', $libelle, $valeur];
    }

    private function vider(): void
    {
        foreach ($this->lignes as [$marque, $libelle, $valeur]) {
            $this->line(sprintf('  %s %-34s %s', $marque, $libelle, $valeur));
        }

        $this->lignes = [];
        $this->newLine();
    }

    /** Ne jamais afficher un jeton en clair : ces sorties finissent copiées-collées. */
    private function masque(string $token): string
    {
        return strlen($token) > 12
            ? substr($token, 0, 6) . str_repeat('•', 8) . substr($token, -4) . ' (' . strlen($token) . ' car.)'
            : '(trop court — suspect)';
    }
}
