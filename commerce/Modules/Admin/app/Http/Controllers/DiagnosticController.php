<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\Admin\AdminOrderMail;
use App\Services\Notifications\AdminNotificationService;
use App\Support\ResourcePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Support\Shop;

/**
 * DiagnosticController
 *
 * Routes de diagnostic accessibles uniquement aux admins connectés.
 * Permet de tester SMTP, emails et notifications sans ligne de commande.
 *
 * Toutes les routes : GET /api/admin/test/...
 */
class DiagnosticController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ResourcePermissions::build([
            'diagnostics.view' => ['smtp', 'mailOrder', 'notifications', 'config'],
        ]);
    }

    // ── Test SMTP brut ────────────────────────────────────────────────────────

    /**
     * GET /api/admin/test/smtp?to=email@exemple.com
     *
     * Envoie un email brut de test pour valider la configuration SMTP.
     */
    public function smtp(Request $request): JsonResponse
    {
        $to   = $request->query('to', config('mail.from.address'));
        $host = config('mail.mailers.smtp.host');
        $port = config('mail.mailers.smtp.port');

        try {
            Mail::raw(
                "✅ Test SMTP " . Shop::name() . "\n\n"
                . "Serveur  : {$host}:{$port}\n"
                . "Envoyé à : {$to}\n"
                . "Heure    : " . now()->toDateTimeString() . "\n\n"
                . "Si vous recevez cet email, la configuration SMTP est correcte.",
                fn ($msg) => $msg
                    ->to($to)
                    ->from(config('mail.from.address'), Shop::name())
                    ->subject('✅ Test SMTP — ' . Shop::name() . ' ' . now()->format('H:i'))
            );

            Log::info('[Diagnostic] SMTP test OK', ['to' => $to]);

            return response()->json([
                'ok'      => true,
                'message' => "Email envoyé à {$to}",
                'config'  => [
                    'host' => $host,
                    'port' => $port,
                    'from' => config('mail.from.address'),
                ],
            ]);

        } catch (\Throwable $e) {
            Log::error('[Diagnostic] SMTP test FAIL', ['error' => $e->getMessage()]);

            return response()->json([
                'ok'      => false,
                'message' => $e->getMessage(),
                'config'  => [
                    'host' => $host,
                    'port' => $port,
                    'from' => config('mail.from.address'),
                ],
            ], 500);
        }
    }

    // ── Test email de commande ────────────────────────────────────────────────

    /**
     * GET /api/admin/test/mail-order?to=email@exemple.com
     *
     * Envoie un AdminOrderMail avec une fausse commande.
     * Valide le template email + la configuration SMTP.
     */
    public function mailOrder(Request $request): JsonResponse
    {
        $to = $request->query('to', config('mail.from.address'));

        $fakePayload = $this->fakOrderPayload($to);

        try {
            Mail::to($to)->send(new AdminOrderMail('order.placed', $fakePayload));

            Log::info('[Diagnostic] Mail order test OK', ['to' => $to]);

            return response()->json([
                'ok'      => true,
                'message' => "Email de commande envoyé à {$to}",
                'order'   => $fakePayload['number'],
            ]);

        } catch (\Throwable $e) {
            Log::error('[Diagnostic] Mail order test FAIL', ['error' => $e->getMessage()]);

            return response()->json([
                'ok'      => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ── Test système de notifications complet ─────────────────────────────────

    /**
     * GET /api/admin/test/notifications?to=email@exemple.com
     *
     * Déclenche AdminNotificationService::dispatch() avec une fausse commande.
     * Teste tous les canaux actifs : email, Reverb, WhatsApp.
     */
    public function notifications(Request $request): JsonResponse
    {
        $to      = $request->query('to', config('mail.from.address'));
        $results = [];

        $fakePayload = $this->fakOrderPayload($to);

        // Test email direct
        try {
            Mail::to($to)->send(new AdminOrderMail('order.placed', $fakePayload));
            $results['email'] = ['ok' => true, 'message' => "Envoyé à {$to}"];
        } catch (\Throwable $e) {
            $results['email'] = ['ok' => false, 'message' => $e->getMessage()];
        }

        // Test AdminNotificationService (tous les canaux)
        try {
            /** @var AdminNotificationService $svc */
            $svc = app(AdminNotificationService::class);
            $svc->dispatch('order.placed', $fakePayload);
            $results['notification_service'] = ['ok' => true, 'message' => 'Dispatché sur tous les canaux'];
        } catch (\Throwable $e) {
            $results['notification_service'] = ['ok' => false, 'message' => $e->getMessage()];
        }

        Log::info('[Diagnostic] Notifications test', $results);

        $allOk = collect($results)->every(fn ($r) => $r['ok']);

        return response()->json([
            'ok'      => $allOk,
            'results' => $results,
            'order'   => $fakePayload['number'],
            'hint'    => 'Consultez storage/logs/laravel.log pour le détail de chaque canal',
        ], $allOk ? 200 : 207);
    }

    // ── Config SMTP (masquée) ─────────────────────────────────────────────────

    /**
     * GET /api/admin/test/config
     *
     * Retourne la configuration SMTP active (mot de passe masqué).
     * Utile pour vérifier que les variables .env sont bien chargées.
     */
    public function config(): JsonResponse
    {
        $password = config('mail.mailers.smtp.password', '');

        return response()->json([
            'mail' => [
                'mailer'     => config('mail.default'),
                'host'       => config('mail.mailers.smtp.host'),
                'port'       => config('mail.mailers.smtp.port'),
                'encryption' => config('mail.mailers.smtp.encryption'),
                'username'   => config('mail.mailers.smtp.username'),
                'password'   => $password ? str_repeat('*', min(strlen($password), 8)) : '(vide)',
                'from'       => config('mail.from.address'),
                'from_name'  => config('mail.from.name'),
            ],
            'app' => [
                'env'   => config('app.env'),
                'url'   => config('app.url'),
                'debug' => config('app.debug'),
            ],
            'whatsapp' => $this->whatsappConfig(),
        ]);
    }

    /**
     * Ce que le serveur croit savoir de WhatsApp.
     *
     * Sans ça, vérifier la configuration de production suppose un accès SSH au
     * container : c'est ce qui a fait chercher pendant des jours une panne qui
     * n'était qu'un jeton périmé et un identifiant de numéro pris pour celui de
     * l'application. Les identifiants sont publics et affichés en clair — seuls
     * le jeton et le secret sont réduits à leurs extrémités, de quoi comparer
     * sans les divulguer.
     */
    private function whatsappConfig(): array
    {
        $token  = (string) config('services.whatsapp.token', '');
        $secret = (string) config('services.whatsapp.app_secret', '');

        return [
            'token'           => $token !== ''
                ? substr($token, 0, 6) . '…' . substr($token, -4) . ' (' . strlen($token) . ' car.)'
                : '(vide)',
            'phone_number_id' => config('services.whatsapp.phone_number_id') ?: '(vide)',
            'waba_id'         => config('services.whatsapp.waba_id') ?: '(vide)',
            'verify_token'    => config('services.whatsapp.verify_token') ? '(défini)' : '(vide)',
            'app_secret'      => $secret !== '' ? '(défini)' : '(vide)',
            'graph_version'   => config('services.whatsapp.graph_version'),
            'templates'       => config('services.whatsapp.templates'),
            'admin_number'    => \Modules\Admin\Models\Setting::get('whatsapp_admin_number', '') ?: '(vide)',
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function fakOrderPayload(string $email): array
    {
        return [
            'id'             => 9999,
            'number'         => 'ORD-TEST-' . strtoupper(substr(uniqid(), -6)),
            'status'         => 'confirmed',
            'customer_name'  => 'Fatou Konaté (TEST)',
            'customer_phone' => '+225 07 00 00 00',
            'customer_email' => $email,
            'payment_method' => 'wave',
            'total'          => 15000,
            'currency'       => 'XOF',
            'commune'        => 'Cocody, Deux Plateaux',
            'items_count'    => 2,
            'items'          => [
                ['name' => 'Élixir de roses', 'qty' => 1, 'price' => 9500, 'variant' => null],
                ['name' => 'Eau de rose classique', 'qty' => 1, 'price' => 5500, 'variant' => null],
            ],
            'created_at' => now()->toISOString(),
            'url'        => url('/admin/orders/9999'),
        ];
    }
}
