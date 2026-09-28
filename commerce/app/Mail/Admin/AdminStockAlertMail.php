<?php

namespace App\Mail\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Support\Shop;

/**
 * Alerte de réassort adressée à la boutique.
 *
 * L'objet porte le nom de l'article : la décision se prend souvent depuis un
 * téléphone, sur la seule ligne d'objet, sans ouvrir le message.
 */
class AdminStockAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $event,
        public readonly array  $payload,
    ) {}

    public function envelope(): Envelope
    {
        $label = $this->event === 'stock.out'
            ? '⛔ Rupture de stock'
            : '⚠️ Stock bas';

        $article = $this->payload['product_name'] ?? 'Article';
        if (! empty($this->payload['variant_label'])) {
            $article .= ' — ' . $this->payload['variant_label'];
        }

        return new Envelope(
            subject: "{$label} : {$article} | " . Shop::name(),
            from: new Address(
                config('mail.from.address'),
                Shop::name(),
            ),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin.stock-alert');
    }
}
