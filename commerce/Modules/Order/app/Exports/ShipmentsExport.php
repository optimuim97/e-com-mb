<?php

namespace Modules\Order\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Modules\Order\Models\Order;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Extraction propre à l'écran des expéditions.
 *
 * OrdersExport ne pouvait pas servir ici : il écarte les commandes non payées
 * hors Abidjan. C'est la bonne règle pour un export commercial — hors Abidjan
 * on n'expédie que ce qui est réglé — mais elle vide précisément les trois
 * onglets de cet écran, dont la raison d'être est de traiter ces commandes-là.
 * Une extraction qui rend une feuille vide sur « À tarifer » n'est pas une
 * extraction filtrée, c'est une extraction cassée.
 *
 * Deux formats. Le complet sert à travailler le dossier ; le format court —
 * client et numéro de commande — sert à ce qu'on emporte : une liste d'appels
 * à passer, un bordereau à cocher, un message à coller au transporteur.
 */
class ShipmentsExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    ShouldAutoSize,
    WithTitle
{
    use Exportable;

    /** Client et numéro de commande, rien d'autre. */
    public const COLUMNS_MINIMAL = 'minimal';

    /** Tout ce qu'il faut pour traiter la commande. */
    public const COLUMNS_FULL = 'full';

    public function __construct(
        /** Onglet de l'écran : 'a_tarifer' | 'interior' | 'international' | null. */
        private readonly ?string $tab = null,
        /** @var int[]|null Sélection explicite, prioritaire sur l'onglet. */
        private readonly ?array $orderIds = null,
        private readonly string $columns = self::COLUMNS_FULL,
        private readonly ?string $label = null,
    ) {}

    public function collection(): Collection
    {
        return self::resolve($this->tab, $this->orderIds);
    }

    /**
     * Commandes retenues.
     *
     * Source de vérité partagée avec l'aperçu : les deux doivent montrer
     * exactement la même liste, faute de quoi l'agent exporte autre chose que
     * ce qu'il a validé à l'écran.
     */
    public static function resolve(?string $tab, ?array $orderIds = null): Collection
    {
        return Order::with(['user', 'items'])
            // Une sélection explicite l'emporte : l'agent a déjà arbitré à
            // l'écran, l'onglet n'a plus à retrancher quoi que ce soit.
            ->when($orderIds, fn ($q, $ids) => $q->whereIn('id', $ids))
            ->when(! $orderIds && $tab === 'a_tarifer', fn ($q) =>
                $q->whereJsonContains('metadata->shipping_unknown', true)
            )
            ->when(! $orderIds && in_array($tab, ['interior', 'international'], true), fn ($q) =>
                $q->where('destination', $tab)
            )
            ->latest()
            ->get();
    }

    private function isMinimal(): bool
    {
        return $this->columns === self::COLUMNS_MINIMAL;
    }

    public function headings(): array
    {
        if ($this->isMinimal()) {
            return ['Client', 'N° Commande'];
        }

        return [
            'Client',
            'N° Commande',
            'Date',
            'Téléphone',
            'Pays',
            'Ville',
            'Commune',
            'Adresse',
            'Statut',
            'Paiement',
            'Frais de livraison',
            'Total',
            'Articles (qté)',
        ];
    }

    public function map($order): array
    {
        $client = $this->clientName($order);

        // Le client d'abord : c'est par lui qu'on cherche dans une feuille
        // imprimée, pas par un numéro de commande qu'on ne retient pas.
        if ($this->isMinimal()) {
            return [$client, $order->number];
        }

        $addr = $order->shipping_address ?? [];

        return [
            $client,
            $order->number,
            $order->created_at->format('d/m/Y H:i'),
            $addr['phone'] ?? '',
            $addr['country'] ?? '',
            $addr['city'] ?? '',
            $addr['commune'] ?? '',
            $addr['address_line1'] ?? '',
            self::STATUS_LABELS[$order->status] ?? $order->status,
            $order->paid_at ? 'Payée' : 'Non payée',
            // Frais non arrêtés : une case vide se remplit à la main, un zéro
            // se lit comme une livraison gratuite.
            $this->shippingPending($order) ? '' : (float) $order->shipping_cost,
            (float) $order->total,
            $order->items->sum('quantity'),
        ];
    }

    private function clientName(Order $order): string
    {
        $addr = $order->shipping_address ?? [];

        return $order->user?->name
            ?: trim(($addr['first_name'] ?? '') . ' ' . ($addr['last_name'] ?? ''))
            ?: '—';
    }

    /** Zone non tarifée dont les frais n'ont pas encore été saisis. */
    private function shippingPending(Order $order): bool
    {
        return (bool) ($order->metadata['shipping_unknown'] ?? false)
            && (float) $order->shipping_cost <= 0;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E8336D'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function title(): string
    {
        return $this->label ?: 'Expéditions';
    }

    private const STATUS_LABELS = [
        'pending'    => 'En attente',
        'confirmed'  => 'Confirmée',
        'processing' => 'En préparation',
        'shipped'    => 'Expédiée',
        'delivered'  => 'Livrée',
        'cancelled'  => 'Annulée',
        'refunded'   => 'Remboursée',
    ];
}
