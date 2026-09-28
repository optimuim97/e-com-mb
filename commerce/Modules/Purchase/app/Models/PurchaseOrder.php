<?php

namespace Modules\Purchase\Models;

use App\Support\Auditing\TracksOperator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un bon d'achat : ce qu'on a commandé à un fournisseur, et ce qu'il a coûté.
 *
 * Cycle de vie volontairement court :
 *   · `draft`    — en préparation, modifiable, sans effet sur le stock ;
 *   · `received` — reçu : les articles sont entrés en stock et les coûts sont
 *                  figés. Un bon reçu ne se modifie plus, sinon il ne
 *                  correspondrait plus à ce qui est déjà en rayon ;
 *   · `cancelled`— abandonné avant réception.
 */
class PurchaseOrder extends Model
{
    use SoftDeletes, TracksOperator;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_RECEIVED  = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_RECEIVED, self::STATUS_CANCELLED];

    public const LABELS = [
        self::STATUS_DRAFT     => 'En préparation',
        self::STATUS_RECEIVED  => 'Reçu',
        self::STATUS_CANCELLED => 'Annulé',
    ];

    protected $fillable = [
        'number', 'supplier_id', 'status', 'currency',
        'items_total', 'shipping_cost', 'other_costs', 'total',
        'ordered_at', 'received_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'items_total'   => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'other_costs'   => 'decimal:2',
            'total'         => 'decimal:2',
            'ordered_at'    => 'date',
            'received_at'   => 'datetime',
        ];
    }

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function items(): HasMany      { return $this->hasMany(PurchaseOrderItem::class); }

    public function getStatusLabelAttribute(): string
    {
        return self::LABELS[$this->status] ?? $this->status;
    }

    /** Un bon encore modifiable : rien n'est entré en stock. */
    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Numéro séquentiel par année, sur le modèle des commandes.
     *
     * `withTrashed()` : un bon supprimé garde son numéro dans les archives
     * comptables. Le réattribuer ferait exister deux pièces différentes sous
     * la même référence.
     */
    public static function generateNumber(): string
    {
        $annee = now()->year;
        $rang  = static::withTrashed()->whereYear('created_at', $annee)->count() + 1;

        return 'ACH-' . $annee . '-' . str_pad((string) $rang, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Frais annexes du bon (transport, douane…), à répartir sur les lignes.
     */
    public function extraCosts(): float
    {
        return (float) $this->shipping_cost + (float) $this->other_costs;
    }
}
