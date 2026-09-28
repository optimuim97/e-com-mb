<?php

namespace Modules\Order\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne de tournée : une commande, dans une sortie donnée.
 *
 * Porte ce que la commande elle-même ne peut pas porter — l'ordre de passage,
 * le résultat du passage, et le montant attendu figé au départ. Une commande
 * qui échoue reparaît dans une tournée suivante avec une nouvelle ligne ;
 * les deux tentatives restent lisibles côte à côte.
 */
class DeliveryRoundOrder extends Model
{
    /** Colonnes exposées quand la ligne est lue comme pivot. */
    public const PIVOT_COLUMNS = [
        'id', 'position', 'status', 'amount_expected', 'amount_collected',
        'payment_method_collected', 'delivered_at', 'failure_reason', 'notes',
        'confirmed_by',
    ];

    public const STATUS_PENDING   = 'pending';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_POSTPONED = 'postponed';

    /** Résultats possibles d'un passage, tels que le pointage les enregistre. */
    public const OUTCOMES = [
        self::STATUS_DELIVERED,
        self::STATUS_FAILED,
        self::STATUS_POSTPONED,
    ];

    protected $fillable = [
        'delivery_round_id', 'order_id', 'position', 'status',
        'amount_expected', 'amount_collected', 'payment_method_collected',
        'delivered_at', 'failure_reason', 'notes', 'confirmed_by',
    ];

    protected function casts(): array
    {
        return [
            'delivered_at'     => 'datetime',
            'amount_expected'  => 'decimal:2',
            'amount_collected' => 'decimal:2',
        ];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(DeliveryRound::class, 'delivery_round_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
