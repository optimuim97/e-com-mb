<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;
use App\Services\Notifications\AdminNotificationService;
use App\Mail\Admin\AdminOrderMail;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ══════════════════════════════════════════════════════════════════════════════
// 🔧 Commandes de test — NE PAS utiliser en production sans sécuriser l'accès
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Test SMTP brut
 * Usage : php artisan test:smtp [--to=email@exemple.com]
 *
 * Envoie un email de diagnostic en passant directement par la config SMTP.
 * Utile pour valider MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD.
 */
Artisan::command('test:smtp {--to= : Adresse de destination (défaut : MAIL_FROM_ADDRESS)}', function () {
    $to   = $this->option('to') ?: config('mail.from.address');
    $from = config('mail.from.address');
    $host = config('mail.mailers.smtp.host');
    $port = config('mail.mailers.smtp.port');

    $this->info("📧 Test SMTP");
    $this->line("   De      : {$from}");
    $this->line("   Vers    : {$to}");
    $this->line("   Serveur : {$host}:{$port}");
    $this->line('');

    try {
        Mail::raw(
            "✅ Test SMTP " . \App\Support\Shop::name() . "\n\n"
            . "Serveur  : {$host}:{$port}\n"
            . "Envoyé à : {$to}\n"
            . "Heure    : " . now()->toDateTimeString() . "\n\n"
            . "Si vous recevez cet email, la configuration SMTP est correcte.",
            function ($message) use ($to, $from) {
                $message
                    ->to($to)
                    ->from($from, \App\Support\Shop::name())
                    ->subject('✅ Test SMTP — ' . \App\Support\Shop::name() . ' ' . now()->format('H:i'));
            }
        );

        $this->info("✅ Email envoyé avec succès à {$to}");
        $this->line('   Vérifiez votre boîte mail (et les spams).');
        Log::info('[test:smtp] Email de test envoyé', ['to' => $to]);

    } catch (\Throwable $e) {
        $this->error("❌ Échec SMTP : " . $e->getMessage());
        $this->line('');
        $this->warn('Vérifiez dans .env :');
        $this->line('   MAIL_MAILER=smtp');
        $this->line('   MAIL_HOST=' . $host);
        $this->line('   MAIL_PORT=' . $port);
        $this->line('   MAIL_USERNAME=...');
        $this->line('   MAIL_PASSWORD=...');
        $this->line('   MAIL_ENCRYPTION=ssl|tls');
        Log::error('[test:smtp] Échec', ['error' => $e->getMessage()]);
    }
})->purpose('Envoie un email de diagnostic pour tester la config SMTP');


/**
 * Test email de commande (AdminOrderMail)
 * Usage : php artisan test:mail-order [--to=email@exemple.com]
 *
 * Envoie un vrai AdminOrderMail avec une commande fictive.
 * Permet de valider le template email + le SMTP en un seul test.
 */
Artisan::command('test:mail-order {--to= : Adresse de destination}', function () {
    $to = $this->option('to') ?: config('mail.from.address');

    $fakePayload = [
        'id'             => 9999,
        'number'         => 'ORD-TEST-' . strtoupper(substr(uniqid(), -6)),
        'status'         => 'confirmed',
        'customer_name'  => 'Fatou Konaté (TEST)',
        'customer_phone' => '+225 07 00 00 00',
        'customer_email' => $to,
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

    $this->info("📬 Test email de commande");
    $this->line("   Destinataire : {$to}");
    $this->line("   Commande     : {$fakePayload['number']}");

    try {
        Mail::to($to)->send(new AdminOrderMail('order.placed', $fakePayload));
        $this->info("✅ Email commande envoyé à {$to}");
    } catch (\Throwable $e) {
        $this->error("❌ Échec : " . $e->getMessage());
    }
})->purpose('Envoie un email de commande test (AdminOrderMail)');


/**
 * Test du système de notifications complet
 * Usage : php artisan test:notifications [--channel=email|reverb|whatsapp|all]
 *
 * Déclenche le AdminNotificationService avec une commande fictive.
 * Teste tous les canaux configurés.
 */
Artisan::command('test:notifications {--channel=all : Canal à tester (email|reverb|whatsapp|all)}', function () {
    $channel = $this->option('channel');

    $this->info("🔔 Test Notifications (canal : {$channel})");
    $this->line('');

    $fakePayload = [
        'id'             => 9999,
        'number'         => 'ORD-NOTIF-TEST',
        'status'         => 'confirmed',
        'customer_name'  => 'Test Client',
        'customer_phone' => '+225 07 00 00 00',
        'customer_email' => config('mail.from.address'),
        'payment_method' => 'wave',
        'total'          => 15000,
        'currency'       => 'XOF',
        'commune'        => 'Cocody',
        'items_count'    => 1,
        'items'          => [
            ['name' => 'Produit test', 'qty' => 1, 'price' => 15000, 'variant' => null],
        ],
        'created_at' => now()->toISOString(),
        'url'        => url('/admin/orders/9999'),
    ];

    try {
        /** @var AdminNotificationService $notifService */
        $notifService = app(AdminNotificationService::class);
        $notifService->dispatch('order.placed', $fakePayload);

        $this->info("✅ Notification dispatchée sur tous les canaux actifs");
        $this->line('   Consultez storage/logs/laravel.log pour le détail');
    } catch (\Throwable $e) {
        $this->error("❌ Erreur : " . $e->getMessage());
    }
})->purpose('Teste le système de notifications (email + Reverb + WhatsApp)');


/**
 * Affiche la config SMTP courante (sans le mot de passe)
 * Usage : php artisan test:smtp-config
 */
Artisan::command('test:smtp-config', function () {
    $this->info('📋 Configuration SMTP courante');
    $this->table(
        ['Paramètre', 'Valeur'],
        [
            ['MAIL_MAILER',     config('mail.default')],
            ['MAIL_HOST',       config('mail.mailers.smtp.host')],
            ['MAIL_PORT',       config('mail.mailers.smtp.port')],
            ['MAIL_ENCRYPTION', config('mail.mailers.smtp.encryption')],
            ['MAIL_USERNAME',   config('mail.mailers.smtp.username')],
            ['MAIL_PASSWORD',   str_repeat('*', min(strlen(config('mail.mailers.smtp.password', '')), 8))],
            ['MAIL_FROM',       config('mail.from.address') . ' <' . config('mail.from.name') . '>'],
        ]
    );
})->purpose('Affiche la configuration SMTP sans le mot de passe');

// ══════════════════════════════════════════════════════════════════════════════
// Tâches planifiées
// ══════════════════════════════════════════════════════════════════════════════

Schedule::command('cart:send-abandonment-reminders')
//    ->hourly()
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/cart-abandonment.log'));

/*
 * Feuille de livraison du soir, par courriel.
 *
 * L'application tourne en UTC et Abidjan est à GMT+0 toute l'année : 21:00
 * ici, c'est bien 21 h à la boutique, sans conversion.
 *
 * Les jours viennent de ShipmentSheetScope::DAYS, qui sert aussi l'extraction
 * manuelle de l'administration. Écrits une seconde fois ici, ils avaient
 * divergé : l'intérieur était extrait le vendredi, alors que la boutique
 * l'expédie ce jour-là et l'extrait donc le jeudi soir.
 *
 * La fenêtre de chaque zone repart de sa dernière extraction, si bien que le
 * dimanche soir reprend naturellement le week-end — sans qu'aucune date ne soit
 * calculée ici.
 */
/*
 * Relance des commandes non réglées, hors Abidjan.
 *
 * À 9 h, en semaine : la boutique est ouverte derrière, prête à répondre. Une
 * relance à 3 h du matin est une relance qu'on ne peut pas suivre.
 *
 * Deux messages au plus, J+1 et J+3. Ce qui vient après n'est pas un message
 * mais une décision : la commande remonte dans l'écran « À traiter » avec une
 * annulation proposée, qu'une agente tranche.
 */
Schedule::command('shop:send-payment-reminders')
    ->dailyAt('09:00')
    ->weekdays()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/payment-reminders.log'));

foreach (array_keys(\Modules\Order\Support\ShipmentSheetScope::ZONES) as $zone) {
    Schedule::command("shop:send-shipment-sheet --zone={$zone}")
        ->dailyAt('21:00')
        ->days(\Modules\Order\Support\ShipmentSheetScope::DAYS[$zone])
        ->withoutOverlapping()
        ->runInBackground()
        ->appendOutputTo(storage_path('logs/shipment-sheet.log'));
}
