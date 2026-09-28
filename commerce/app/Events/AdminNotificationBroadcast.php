<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Événement générique broadcasté sur le canal privé admin.
 * Toutes les notifications temps-réel admin passent par ici.
 *
 * Canal frontend : Echo.private('admin.notifications')
 * Écoute        : .AdminNotification
 */
class AdminNotificationBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $event,
        public readonly array  $payload,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.notifications')];
    }

    /** Nom de l'événement côté frontend */
    public function broadcastAs(): string
    {
        return 'AdminNotification';
    }

    /** Ce qui est envoyé au frontend */
    public function broadcastWith(): array
    {
        return [
            'event'     => $this->event,
            'payload'   => $this->payload,
            'timestamp' => now()->toISOString(),
        ];
    }
}
