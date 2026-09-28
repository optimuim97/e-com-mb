<?php

namespace Modules\Order\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Modules\Order\Mail\OrderConfirmationMail;
use Modules\Order\Models\Order;

/**
 * Commande de test : envoie un mail de confirmation à une adresse arbitraire
 * en utilisant une commande réelle (la plus récente par défaut).
 *
 * Usage :
 *   php artisan order:test-mail client@exemple.com
 *   php artisan order:test-mail client@exemple.com --order=ORD-2026-00001
 *   php artisan order:test-mail client@exemple.com --sync   # ignore la queue
 */
class TestConfirmationMailCommand extends Command
{
    protected $signature = 'order:test-mail
                            {email : Adresse e-mail destinataire}
                            {--order= : Numéro de commande (ORD-XXXX). Par défaut : la plus récente}
                            {--sync : Envoyer immédiatement sans passer par la queue}';

    protected $description = "Envoie un e-mail de test (confirmation + facture PDF) à l'adresse donnée";

    public function handle(): int
    {
        $email = $this->argument('email');

        $order = $this->option('order')
            ? Order::where('number', $this->option('order'))->first()
            : Order::latest()->first();

        if (!$order) {
            $this->error("Aucune commande trouvée (DB vide ou numéro inconnu).");
            return self::FAILURE;
        }

        $order->loadMissing(['user', 'items.product', 'payments']);

        $this->info("Commande utilisée : {$order->number} (total " . number_format($order->total, 0, ',', ' ') . " FCFA)");
        $this->info("Destinataire      : {$email}");
        $this->line('');

        try {
            $mailable = new OrderConfirmationMail($order);
            if ($this->option('sync')) {
                Mail::to($email)->sendNow($mailable);
                $this->info('✅ Mail envoyé en synchrone.');
            } else {
                Mail::to($email)->send($mailable);
                $this->info('✅ Mail empilé sur la queue (lance `php artisan queue:work --queue=notifications` si QUEUE_CONNECTION != sync).');
            }
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Échec : ' . $e->getMessage());
            $this->warn('Vérifie MAIL_MAILER / MAIL_HOST / MAIL_USERNAME dans .env');
            return self::FAILURE;
        }
    }
}
