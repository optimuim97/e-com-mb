<?php

namespace Modules\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Catalog\Models\StockMovement;

/**
 * Le stock d'un article vient de passer sous un seuil d'alerte.
 *
 * Émis au franchissement seulement, jamais tant qu'on reste en dessous : sinon
 * chaque vente de la journée rejouerait la même alerte, et on cesserait très
 * vite de les lire. Repasser au-dessus du seuil réarme l'alerte.
 */
class StockAlertRaised
{
    use Dispatchable;

    /** Le dernier article est parti. */
    public const LEVEL_OUT = 'out';
    /** Il reste du stock, mais sous le seuil de réassort. */
    public const LEVEL_LOW = 'low';

    public function __construct(
        public readonly Product         $product,
        public readonly ?ProductVariant $variant,
        public readonly StockMovement   $movement,
        public readonly string          $level,
    ) {}

    public function isOutOfStock(): bool
    {
        return $this->level === self::LEVEL_OUT;
    }
}
