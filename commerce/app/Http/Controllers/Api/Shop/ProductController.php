<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function index(Request $request): ResourceCollection
    {
        $products = Product::where('is_active', true)
            ->with(['images', 'category'])
            ->when($request->filled('category'), fn($q) =>
                $q->whereHas('category', fn($c) => $c->where('slug', $request->category))
            )
            ->when($request->filled('search'), fn($q) =>
                $q->where(fn($s) =>
                    $s->where('name', 'like', '%'.$request->search.'%')
                      ->orWhere('short_description', 'like', '%'.$request->search.'%')
                )
            )
            ->when($request->filled('min_price'), fn($q) => $q->where('price', '>=', $request->min_price))
            ->when($request->filled('max_price'),  fn($q) => $q->where('price', '<=', $request->max_price))
            ->when($request->boolean('featured'),  fn($q) => $q->where('is_featured', true))
            ->orderBy(...match($request->sort) {
                'price_asc'  => ['price', 'asc'],
                'price_desc' => ['price', 'desc'],
                default      => ['created_at', 'desc'],
            })
            ->paginate($request->integer('per_page', 24));

        // Cache HTTP côté navigateur : 5 min pour les requêtes sans filtre
        $cacheHeader = $request->hasAny(['search', 'min_price', 'max_price'])
            ? 'no-store'
            : 'public, max-age=300, stale-while-revalidate=60';

        return ProductResource::collection($products)
            ->response()
            ->header('Cache-Control', $cacheHeader);
    }

    public function show(string $slug): ProductResource
    {
        $product = Cache::remember("shop.product.{$slug}", 300, fn() =>
            Product::where('slug', $slug)
                ->where('is_active', true)
                ->with(['images', 'category', 'variants.attributeValues.attribute', 'attributes.values'])
                ->firstOrFail()
        );

        return (new ProductResource($product))
            ->response()
            ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60');
    }
}
