<?php

namespace Modules\Cart\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Cart\Models\Cart;

class AbandonedCartMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param Cart   $cart   The abandoned cart (with items.product loaded)
     * @param int    $day    1 or 3 — which reminder wave this is
     */
    public function __construct(
        public readonly Cart $cart,
        public readonly int  $day = 1,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->day === 1
            ? '🛒 Vous avez oublié quelque chose, ' . ($this->cart->user->name ?? 'belle') . ' 💄'
            : '✨ Dernière chance — votre panier vous attend encore !';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'cart::emails.abandoned_cart',
            with: [
                'cart'         => $this->cart,
                'day'          => $this->day,
                'customerName' => $this->cart->user?->name ?? 'belle',
                'cartUrl'      => config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173')) . '/cart',
                'shopUrl'      => config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173')),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
