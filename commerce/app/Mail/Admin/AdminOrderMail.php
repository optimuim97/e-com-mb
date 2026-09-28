<?php

namespace App\Mail\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Support\Shop;

class AdminOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $event,
        public readonly array  $payload,
    ) {}

    public function envelope(): Envelope
    {
        $label = match ($this->event) {
            'order.placed'    => '🛒 Nouvelle commande',
            'order.shipped'   => '🚚 Commande expédiée',
            'order.cancelled' => '❌ Commande annulée',
            default           => '🔔 Notification boutique',
        };

        $number = $this->payload['number'] ?? '';

        return new Envelope(
            subject: "{$label} — {$number} | " . Shop::name(),
            from: new Address(
                config('mail.from.address'),
                Shop::name(),
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.order',
        );
    }
}
