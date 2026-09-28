<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'slug'              => $this->slug,
            'description'       => $this->description,
            'short_description' => $this->short_description,
            'price'             => $this->price,
            'compare_price'     => $this->compare_price,
            'discount_percent'  => $this->compare_price
                ? round((1 - $this->price / $this->compare_price) * 100)
                : null,
            'sku'               => $this->sku,
            'stock'             => $this->effective_stock,
            'type'              => $this->type,
            'has_variants'      => $this->has_variants,
            'is_active'         => $this->is_active,
            'is_featured'       => $this->is_featured,
            'category'          => $this->whenLoaded('category', fn() => new CategoryResource($this->category)),
            'images'            => ProductImageResource::collection($this->whenLoaded('images')),
            'variants'          => VariantResource::collection($this->whenLoaded('variants')),
            'created_at'        => $this->created_at,
        ];
    }
}
