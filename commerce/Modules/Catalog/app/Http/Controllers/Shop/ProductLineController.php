<?php

namespace Modules\Catalog\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Modules\Catalog\Http\Resources\ProductLineResource;
use Modules\Catalog\Http\Resources\ProductResource;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductLineController extends Controller
{
    public function index(): ResourceCollection
    {
        $lines = ProductLine::where('is_active', true)
            ->withCount(['products' => fn($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Produits composants : noms (décor de repli des cartes) + somme des
        // prix (= prix affiché de la gamme). Source : la composition pivot
        // (admin → Composition) en priorité, sinon le champ items (slugs).
        $lines->load(['products' => fn ($q) => $q
            ->where('is_active', true)
            ->select('products.id', 'products.name', 'products.price')]);

        $allSlugs = $lines->pluck('items')->filter()->flatten()->unique()->values();
        $bySlug   = $allSlugs->isNotEmpty()
            ? Product::whereIn('slug', $allSlugs)->where('is_active', true)
                ->get(['slug', 'name', 'price'])->keyBy('slug')
            : collect();

        $lines->each(function (ProductLine $line) use ($bySlug) {
            $components = $line->products->isNotEmpty()
                ? $line->products
                : collect($line->items ?? [])->map(fn ($slug) => $bySlug[$slug] ?? null)->filter()->values();

            $line->item_names  = $components->pluck('name')->all();
            // Le prix de la gamme = somme des prix de ses composants
            $line->items_total = (float) $components->sum('price');

            // Ne pas sérialiser la relation complète dans la réponse liste
            $line->unsetRelation('products');
        });

        return ProductLineResource::collection($lines);
    }

    public function show(string $slug, Request $request): ProductLineResource
    {
        $line = ProductLine::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        // Produits constituant la gamme (depuis le champ items — slugs ordonnés)
        if (!empty($line->items)) {
            $itemSlugs    = $line->items;
            $itemProducts = Product::whereIn('slug', $itemSlugs)
                ->where('is_active', true)
                ->with(['images'])
                ->get()
                ->sortBy(fn($p) => array_search($p->slug, $itemSlugs))
                ->values();

            $line->setRelation('itemsProducts', $itemProducts);
        }

        // Produits additionnels paginés (via product_line_id) pour le bas de page
        $products = $line->products()
            ->where('is_active', true)
            ->with(['images', 'category'])
            ->when($request->filled('sort'), fn($q) => match($request->sort) {
                'price_asc'  => $q->orderBy('price', 'asc'),
                'price_desc' => $q->orderBy('price', 'desc'),
                default      => $q->latest(),
            }, fn($q) => $q->latest())
            ->paginate($request->integer('per_page', 24));

        $line->setRelation('products', $products);

        return new ProductLineResource($line);
    }
}
