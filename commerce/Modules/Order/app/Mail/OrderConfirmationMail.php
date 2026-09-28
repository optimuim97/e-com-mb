<?php

namespace Modules\Order\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Order\Models\Order;
use Modules\Order\Services\InvoiceService;
use Modules\Order\Support\CustomerEmail;
use Modules\Order\Support\ShopMailbox;
use App\Support\Shop;

class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirmation de votre commande ' . $this->order->number . ' — ' . Shop::name(),
            // La boutique garde une trace de ce qui a été promis, à qui, et
            // quand. En copie cachée : la cliente n'a pas à voir l'adresse
            // interne, et une copie visible ferait répondre à la mauvaise boîte.
            bcc: ShopMailbox::followUpBcc(CustomerEmail::for($this->order)),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'order::emails.order-confirmation',
            with: ['order' => $this->order],
        );
    }

    /**
     * Attache la facture PDF générée à la volée.
     */
    public function attachments(): array
    {
        $invoiceService = app(InvoiceService::class);
        $pdf = $invoiceService->generate($this->order);

        return [
            Attachment::fromData(fn () => $pdf->output(), $invoiceService->filename($this->order))
                ->withMime('application/pdf'),
        ];
    }
}
