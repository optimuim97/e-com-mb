<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Illuminate\Support\Str;

class ProductData extends Data
{
    public function __construct(
        public readonly string  $name,
        public readonly string  $slug,
        public readonly ?string $description,
        public readonly ?string $short_description,
        public readonly float   $price,
        public readonly ?float  $compare_price,
        public readonly ?float  $cost_price,
        public readonly ?string $sku,
        public readonly int     $stock,
        public readonly string  $type,
        public readonly ?int    $category_id,
        public readonly bool    $is_active,
        public readonly bool    $is_featured,
        public readonly bool    $track_stock,
        public readonly ?float  $weight,
    ) {}

    public static function fromRequest(array $validated): self
    {
        $slug = Str::slug($validated['name']);
        return new self(
            name:              $validated['name'],
            slug:              $slug,
            description:       $validated['description'] ?? null,
            short_description: $validated['short_description'] ?? null,
            price:             (float) $validated['price'],
            compare_price:     isset($validated['compare_price']) ? (float) $validated['compare_price'] : null,
            cost_price:        isset($validated['cost_price']) ? (float) $validated['cost_price'] : null,
            sku:               $validated['sku'] ?? null,
            stock:             (int) $validated['stock'],
            type:              $validated['type'],
            category_id:       $validated['category_id'] ?? null,
            is_active:         (bool) ($validated['is_active'] ?? true),
            is_featured:       (bool) ($validated['is_featured'] ?? false),
            track_stock:       (bool) ($validated['track_stock'] ?? true),
            weight:            isset($validated['weight']) ? (float) $validated['weight'] : null,
        );
    }
}
