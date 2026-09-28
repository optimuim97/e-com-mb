<?php

namespace App\Services\Notifications\Channels;

use App\Mail\Admin\AdminOrderMail;
use App\Mail\Admin\AdminStockAlertMail;
use App\Services\Notifications\Contracts\NotificationChannelInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Admin\Models\Setting;

class AdminEmailChannel implements NotificationChannelInterface
{
    public function notify(string $event, array $payload): void
    {
        $adminEmail = Setting::get('shop_email')
            ?: config('mail.from.address');

        if (! $adminEmail) {
            Log::warning('[EmailChannel] Aucun email admin configuré.');
            return;
        }

        // Refuse les domaines placeholder/non routables pour éviter les bounces.
        if (! $this->isRealEmail($adminEmail)) {
            Log::warning("[EmailChannel] Email admin invalide ({$adminEmail}). Renseignez shop_email dans l'admin.");
            return;
        }

        // Une alerte de réassort n'a ni numéro ni cliente : la mettre dans le
        // gabarit « commande » donnerait un message aux trois quarts vide.
        $message = str_starts_with($event, 'stock.')
            ? new AdminStockAlertMail($event, $payload)
            : new AdminOrderMail($event, $payload);

        Mail::to($adminEmail)->send($message);

        Log::info("[EmailChannel] Email envoyé pour {$event}", ['to' => $adminEmail]);
    }

    private function isRealEmail(string $email): bool
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

        $email  = strtolower($email);
        $domain = substr(strrchr($email, '@'), 1);

        $blockedTlds = ['local', 'invalid', 'test', 'example', 'localhost'];
        foreach ($blockedTlds as $tld) {
            if (str_ends_with($domain, '.' . $tld) || $domain === $tld) return false;
        }

        $blockedDomains = ['clients.local', 'boutique.local', 'commerce.local', 'localhost'];
        return ! in_array($domain, $blockedDomains, true);
    }

    public function shouldNotify(string $event): bool
    {
        // Commandes et alertes de réassort. Le réassort se décide sur
        // plusieurs jours : il lui faut une trace écrite, pas seulement une
        // pastille dans le back-office qu'on verra peut-être.
        return str_starts_with($event, 'order.')
            || str_starts_with($event, 'stock.');
    }
}
