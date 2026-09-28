<?php

namespace Modules\Order\Exports;

use Carbon\Carbon;
use Modules\Order\Models\Order;
use Modules\Order\Support\AbidjanZone;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrdersExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    ShouldAutoSize,
    WithTitle
{
    use Exportable;

    /**
     * Statuts écartés tant qu'aucun n'est explicitement demandé.
     *
     * Une commande annulée ou remboursée n'est pas une vente : la laisser dans
     * l'extraction fausse les totaux de la feuille et allonge la liste de
     * lignes dont personne n'a rien à faire.
     */
    private const NOT_A_SALE = ['cancelled', 'refunded'];

    public function __construct(
        private readonly ?string $dateFrom  = null,
        private readonly ?string $dateTo    = null,
        private readonly ?string $status    = null,
        private readonly ?string $country   = null,
        /** @var int[]|null Restreint l'export à une sélection précise (aperçu admin). */
        private readonly ?array  $orderIds  = null,
        private readonly bool    $includeCancelled = false,
    ) {}

    public function collection(): Collection
    {
        return self::resolve([
            'date_from'         => $this->dateFrom,
            'date_to'           => $this->dateTo,
            'status'            => $this->status,
            'country'           => $this->country,
            'order_ids'         => $this->orderIds,
            'include_cancelled' => $this->includeCancelled,
        ]);
    }

    /**
     * Sélection des commandes exportables.
     *
     * Source de vérité partagée entre l'export réel et l'aperçu admin : les
     * deux DOIVENT afficher exactement le même jeu de commandes.
     */
    public static function resolve(array $filters): Collection
    {
        $status = $filters['status'] ?? null;

        return Order::with(['user', 'items', 'payments'])
                    ->when($status, fn($q, $v) => $q->where('status', $v))
                    // Demander « Annulées » reste possible : c'est en l'absence
                    // de statut choisi que l'extraction se limite aux ventes.
                    ->when(
                        ! $status && empty($filters['include_cancelled']),
                        fn($q) => $q->whereNotIn('status', self::NOT_A_SALE)
                    )
                    ->when(self::bound($filters['date_from'] ?? null, true),  fn($q, $v) => $q->where('created_at', '>=', $v))
                    ->when(self::bound($filters['date_to']   ?? null, false), fn($q, $v) => $q->where('created_at', '<=', $v))
                    ->when($filters['country']   ?? null, fn($q, $v) => $q->whereJsonContains('shipping_address->country', $v))
                    ->when($filters['order_ids'] ?? null, fn($q, $v) => $q->whereIn('id', $v))
                    ->latest()
                    ->get()
                    // Règle métier : hors Abidjan, on n'expédie que ce qui est déjà payé.
                    // On exclut donc de l'export les commandes non payées hors Abidjan.
                    ->reject(fn(Order $order) =>
                        is_null($order->paid_at) && !AbidjanZone::isAbidjan($order->shipping_address)
                    )
                    ->values();
    }

    /**
     * Borne de la plage, à l'heure près.
     *
     * La comparaison portait sur `whereDate`, qui ignore l'heure : impossible
     * d'extraire la matinée. Comparer le timestamp le permet, mais une date
     * nue y vaudrait minuit — « au 9 septembre » aurait alors amputé toute la
     * journée du 9. Une date sans heure couvre donc son jour entier, du
     * premier au dernier instant ; une date suivie d'une heure est prise
     * telle quelle.
     */
    private static function bound(?string $value, bool $isStart): ?Carbon
    {
        if (! $value) {
            return null;
        }

        $moment = Carbon::parse($value);

        if (preg_match('/\d{1,2}:\d{2}/', $value)) {
            return $moment;
        }

        return $isStart ? $moment->startOfDay() : $moment->endOfDay();
    }

    public function headings(): array
    {
        return [
            'N° Commande',
            'Date',
            'Client',
            'Email',
            'Téléphone',
            'Pays',
            'Ville',
            'Commune',
            'Statut',
            'Statut paiement',
            'Méthode de paiement',
            'Sous-total',
            'Réduction',
            'Frais de livraison',
            'Total',
            'Devise',
            'Coupon',
            'Articles (qté)',
            'Payée le',
        ];
    }

    public function map($order): array
    {
        $addr    = $order->shipping_address ?? [];
        $payment = $order->payments->first();

        $providerMap = [
            'wave'         => 'Wave',
            'orange_money' => 'Orange Money',
            'stripe'       => 'Stripe',
            'cod'          => 'Paiement à la livraison',
            'virement'     => 'Virement bancaire',
            'delivery'     => 'Paiement à la livraison',
        ];

        $statusMap = [
            'pending'    => 'En attente',
            'confirmed'  => 'Confirmée',
            'processing' => 'En cours',
            'shipped'    => 'Expédiée',
            'delivered'  => 'Livrée',
            'cancelled'  => 'Annulée',
            'refunded'   => 'Remboursée',
        ];

        return [
            $order->number,
            $order->created_at->format('d/m/Y H:i'),
            $order->user?->name ?? trim(($addr['first_name'] ?? '') . ' ' . ($addr['last_name'] ?? '')),
            $order->user?->email ?? '',
            $addr['phone'] ?? '',
            $addr['country'] ?? '',
            $addr['city'] ?? '',
            $addr['commune'] ?? '',
            $statusMap[$order->status] ?? $order->status,
            $order->paid_at ? 'Payée' : 'Non payée',
            $payment ? ($providerMap[$payment->provider] ?? $payment->provider) : '',
            (float) $order->subtotal,
            (float) $order->discount_amount,
            (float) $order->shipping_cost,
            (float) $order->total,
            $order->currency,
            $order->coupon_code ?? '',
            $order->items->sum('quantity'),
            $order->paid_at?->format('d/m/Y H:i') ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Header row style
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
        return 'Commandes';
    }
}
