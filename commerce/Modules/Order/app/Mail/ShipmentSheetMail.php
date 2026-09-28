<?php

namespace Modules\Order\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Feuille de livraison du soir, en pièce jointe.
 *
 * Le texte brut est joint tel quel plutôt que recopié dans le corps du
 * message : c'est le même fichier que celui téléchargé depuis l'écran des
 * expéditions, imprimable sur la thermique et collable dans WhatsApp. Un
 * courriel mis en forme ne s'imprime pas de la même façon, et deux versions
 * de la même feuille finissent par diverger.
 */
class ShipmentSheetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        /** Intitulé de la zone : « Abidjan » ou « Hors Abidjan ». */
        public readonly string $zoneLabel,
        /** La feuille, en texte brut. */
        public readonly string $sheet,
        public readonly int $ordersCount,
        public readonly string $filename,
        /** @var array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}|null */
        public readonly ?array $window = null,
        /**
         * Liste des retardataires plutôt que feuille du jour. L'objet est le
         * même — une liste de commandes — mais l'action attendue diffère : ici
         * il faut débloquer, pas expédier. L'objet du message doit le dire,
         * sinon les deux courriels se confondent dans la boîte.
         */
        public readonly bool $pending = false,
        /**
         * La tournée ouverte en brouillon pour cette feuille, s'il y en a une.
         *
         * @var array{code: string, count: int, items: int, skipped: int}|null
         */
        public readonly ?array $round = null,
    ) {}

    public function envelope(): Envelope
    {
        $objet = $this->pending
            ? "À traiter — {$this->zoneLabel} — {$this->ordersCount} commande(s) en attente"
            : "Feuille de livraison — {$this->zoneLabel} — " . now()->format('d/m/Y');

        // Le code dans l'objet : c'est par lui qu'on retrouve le courriel de la
        // veille quand on cherche de quoi était faite la tournée du matin.
        if ($this->round !== null) {
            $objet .= " — {$this->round['code']}";
        }

        return new Envelope(subject: $objet);
    }

    public function content(): Content
    {
        return new Content(
            view: 'order::emails.shipment-sheet',
            with: [
                'zoneLabel'   => $this->zoneLabel,
                'ordersCount' => $this->ordersCount,
                'sheet'       => $this->sheet,
                'pending'     => $this->pending,
                'window'      => $this->window,
                'round'       => $this->round,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->sheet, $this->filename)
                ->withMime('text/plain'),
        ];
    }
}
