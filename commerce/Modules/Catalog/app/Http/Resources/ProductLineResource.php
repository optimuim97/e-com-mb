<?php

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'slug'             => $this->slug,
            'tagline'          => $this->tagline,
            'description'      => $this->description,
            'price'            => $this->price,
            'cover_url'        => $this->cover_url,
            'color_hex'        => $this->color_hex,
            'is_active'        => $this->is_active,
            'sort_order'       => $this->sort_order,
            'products_count'   => $this->whenCounted('products', fn() => $this->products_count),
            'item_names'       => $this->item_names ?? [],
            // Somme des prix des produits composants (= prix affiché de la gamme)
            'items_total'      => $this->items_total ?? null,
            'products'         => ProductResource::collection($this->whenLoaded('products')),
            'items_products'   => ProductResource::collection($this->whenLoaded('itemsProducts')),
        ];
    }
}
