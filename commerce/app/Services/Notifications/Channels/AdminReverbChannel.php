<?php

namespace App\Services\Notifications\Channels;

use App\Events\AdminNotificationBroadcast;
use App\Services\Notifications\Contracts\NotificationChannelInterface;
use Illuminate\Support\Facades\Log;

class AdminReverbChannel implements NotificationChannelInterface
{
    public function notify(string $event, array $payload): void
    {
        broadcast(new AdminNotificationBroadcast($event, $payload));

        Log::info("[ReverbChannel] Broadcast {$event}", ['number' => $payload['number'] ?? null]);
    }

    public function shouldNotify(string $event): bool
    {
        // Tous les événements admin sont broadcastés
        return true;
    }
}
