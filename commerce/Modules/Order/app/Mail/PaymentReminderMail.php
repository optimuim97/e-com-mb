<?php

namespace Modules\Order\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Order\Models\Order;
use Modules\Order\Support\CustomerEmail;
use Modules\Order\Support\DeliveryEstimate;
use Modules\Order\Support\ShopMailbox;

/**
 * Reminds a customer that her order is waiting for payment.
 *
 * Outside Abidjan nothing ships unpaid: without this message the order waits
 * in silence until someone notices, and a customer who thinks she has ordered
 * simply never receives anything.
 *
 * The tone is a reminder, not a threat: the shop wants the sale, and the
 * message carries what she needs to finish it — the amount, how to pay, and the
 * date the parcel would leave.
 */
class PaymentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        /** 1 or 3: which of the two reminders this is. */
        public readonly int $day,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->day === 1
                ? 'Votre commande ' . $this->order->number . ' attend votre règlement'
                : 'Dernier rappel : votre commande ' . $this->order->number,
            // La boutique est en copie : c'est ainsi qu'elle voit passer les
            // relances, et qu'elle sait quoi dire si la cliente rappelle.
            bcc: ShopMailbox::followUpBcc(CustomerEmail::for($this->order)),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'order::emails.payment-reminder',
            with: [
                'order'    => $this->order,
                'day'      => $this->day,
                // La date promise donne une raison de régler maintenant.
                'estimate' => DeliveryEstimate::sentence(DeliveryEstimate::for($this->order)),
            ],
        );
    }
}
