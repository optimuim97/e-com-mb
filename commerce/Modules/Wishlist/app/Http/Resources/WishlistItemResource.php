<?php

namespace Modules\Wishlist\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Services\OnlineAvailability;

class WishlistItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product = $this->product;
        $cover   = $product?->images->firstWhere('is_cover', true) ?? $product?->images?->first();

        // Même règle que la fiche : la liste d'envies est justement l'endroit
        // où la cliente revient voir si l'article est de nouveau disponible.
        $disponibilite = app(OnlineAvailability::class)->for($product);

        return [
            'id'               => $this->id,
            'product_id'       => $product->id,
            'product_name'     => $product->name,
            'product_slug'     => $product->slug,
            'product_price'    => $product->price,
            'product_compare'  => $product->compare_price,
            'discount_percent' => $product->compare_price
                ? round((1 - $product?->price / $product?->compare_price) * 100)
                : null,
            'product_image'    => $cover?->url,
            // Le vendable en ligne, pas la réserve. Nul = aucune limite.
            'product_stock'        => $disponibilite['sellable'],
            'product_availability' => $disponibilite['status'],
            'is_active'        => $product->is_active,
            'added_at'         => $this->created_at,
        ];
    }
}
