<?php

namespace Modules\Catalog\Models;

use App\Support\Auditing\TracksOperator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Quantité qu'un article a le droit de vendre en ligne sur une semaine.
 *
 * Ne porte que la décision. Le vendu, l'entré et le restant se calculent —
 * voir WeeklyStockBoard.
 */
class WeeklyStockAllocation extends Model
{
    use TracksOperator;

    protected $fillable = ['product_id', 'variant_id', 'week_start', 'quantity', 'note'];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'quantity'   => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * Lundi de la semaine d'une date.
     *
     * Un seul endroit décide où commence la semaine : l'écran, le plafond de
     * vente et les statistiques doivent tomber d'accord, faute de quoi une
     * commande du lundi matin se retrouverait comptée sur la semaine passée.
     */
    public static function weekOf(Carbon|string|null $date = null): Carbon
    {
        return Carbon::parse($date ?? now())->startOfWeek()->startOfDay();
    }

    /**
     * Allocations d'une semaine.
     *
     * `whereDate` et non une égalité : selon le moteur, une colonne `date`
     * peut être relue avec son heure à zéro (« 2026-09-07 00:00:00 »), et la
     * comparaison à « 2026-09-07 » ne trouve alors jamais rien. Le symptôme
     * est sournois — l'écriture réussit, la lecture rend vide, et chaque
     * enregistrement crée un doublon au lieu de mettre à jour.
     */
    public function scopeForWeek($query, Carbon|string|null $week = null)
    {
        return $query->whereDate('week_start', self::weekOf($week)->toDateString());
    }

    /** L'allocation d'un article pour une semaine, s'il y en a une. */
    public static function lookup(int $productId, ?int $variantId, Carbon|string|null $week = null): ?self
    {
        return self::query()
            ->forWeek($week)
            ->where('product_id', $productId)
            ->when($variantId === null,
                fn ($q) => $q->whereNull('variant_id'),
                fn ($q) => $q->where('variant_id', $variantId))
            ->first();
    }
}
