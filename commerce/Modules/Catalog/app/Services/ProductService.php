<?php

namespace Modules\Catalog\Services;

use Modules\Catalog\Data\ProductData;
use Modules\Catalog\Models\Product;
use Illuminate\Support\Str;

class ProductService
{
    public function create(ProductData $data): Product
    {
        $slug = $this->uniqueSlug($data->slug);
        return Product::create([...$data->toArray(), 'slug' => $slug]);
    }

    public function update(Product $product, ProductData $data): Product
    {
        $attrs = $data->toArray();
        if ($data->name !== $product->name) {
            $attrs['slug'] = $this->uniqueSlug($data->slug, $product->id);
        }
        $product->update($attrs);
        return $product->fresh();
    }

    public function delete(Product $product): void
    {
        $product->delete(); // soft delete
    }

    private function uniqueSlug(string $base, ?int $excludeId = null): string
    {
        $slug = $base;
        $i    = 1;
        while (Product::where('slug', $slug)->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
