<?php

namespace Modules\Catalog\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Une entrée ou une sortie de stock, déjà appliquée au compteur.
 *
 * Ces lignes ne se modifient pas : corriger une erreur, c'est enregistrer un
 * mouvement inverse. Un historique réécrit ne prouverait plus rien.
 */
class StockMovement extends Model
{
    /** Vente à une cliente (boutique, commande rapide ou caisse). */
    public const TYPE_SALE         = 'sale';
    /** Retour au stock après annulation d'une commande. */
    public const TYPE_CANCELLATION = 'cancellation';
    /** Entrée consécutive à un achat fournisseur. */
    public const TYPE_PURCHASE     = 'purchase';
    /** Correction manuelle après comptage. */
    public const TYPE_ADJUSTMENT   = 'adjustment';
    /** Retour d'une cliente. */
    public const TYPE_RETURN       = 'return';
    /** Reprise en masse (import de fichier d'inventaire). */
    public const TYPE_IMPORT       = 'import';
    /** Casse, perte, péremption. */
    public const TYPE_LOSS         = 'loss';

    public const TYPES = [
        self::TYPE_SALE, self::TYPE_CANCELLATION, self::TYPE_PURCHASE,
        self::TYPE_ADJUSTMENT, self::TYPE_RETURN, self::TYPE_IMPORT,
        self::TYPE_LOSS,
    ];

    /** Libellés affichés dans l'historique. */
    public const LABELS = [
        self::TYPE_SALE         => 'Vente',
        self::TYPE_CANCELLATION => 'Annulation',
        self::TYPE_PURCHASE     => 'Entrée achat',
        self::TYPE_ADJUSTMENT   => 'Inventaire',
        self::TYPE_RETURN       => 'Retour client',
        self::TYPE_IMPORT       => 'Import',
        self::TYPE_LOSS         => 'Perte',
    ];

    protected $fillable = [
        'product_id', 'variant_id', 'type', 'quantity',
        'stock_before', 'stock_after', 'unit_cost', 'reason',
        'reference_type', 'reference_id', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity'     => 'integer',
            'stock_before' => 'integer',
            'stock_after'  => 'integer',
            'unit_cost'    => 'decimal:2',
        ];
    }

    public function product(): BelongsTo  { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo  { return $this->belongsTo(ProductVariant::class, 'variant_id'); }
    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function reference(): MorphTo  { return $this->morphTo(); }

    public function getLabelAttribute(): string
    {
        return self::LABELS[$this->type] ?? $this->type;
    }

    /** Entrée (positive) ou sortie (négative) ? */
    public function getDirectionAttribute(): string
    {
        return $this->quantity >= 0 ? 'in' : 'out';
    }

    public function scopeEntrees($query)  { return $query->where('quantity', '>', 0); }
    public function scopeSorties($query)  { return $query->where('quantity', '<', 0); }
}
