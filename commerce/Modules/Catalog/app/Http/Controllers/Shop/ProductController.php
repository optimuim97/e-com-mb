<?php

namespace Modules\Catalog\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Modules\Catalog\Http\Resources\ProductResource;
use Modules\Catalog\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductController extends Controller
{
    public function index(Request $request): ResourceCollection
    {
        $products = Product::where('is_active', true)
            ->withAvg(['reviews as reviews_avg_rating' => fn($q) => $q->where('status', 'approved')], 'rating')
            ->withCount(['reviews as reviews_count' => fn($q) => $q->where('status', 'approved')])
            ->with(['images', 'category', 'productLine'])
            ->when($request->filled('category'), fn($q) =>
                // Pivot multi-catégories : trouve le produit dans toutes ses catégories
                $q->whereHas('categories', fn($c) => $c->where('slug', $request->category))
            )
            ->when($request->filled('product_line'), fn($q) =>
                $q->whereHas('productLine', fn($c) => $c->where('slug', $request->product_line))
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
            // Articles d'une opération promotionnelle : c'est la destination du
            // bouton de la bannière d'accueil. Une opération globale ne rattache
            // aucun article — le filtre ne s'applique donc qu'aux opérations
            // ciblées, et la bannière renvoie au catalogue entier dans l'autre cas.
            ->when($request->filled('promotion'), fn($q) =>
                $q->whereHas('promotions', fn($p) =>
                    $p->where('promotions.id', $request->integer('promotion'))->activeNow()
                )
            )
            // Les épuisés ferment la marche, quel que soit le tri demandé : la
            // cliente qui trie par prix croissant n'a pas envie de tomber
            // d'abord sur ce qu'elle ne peut pas acheter.
            ->orderByAvailability()
            ->orderBy(...match($request->sort) {
                'price_asc'  => ['price', 'asc'],
                'price_desc' => ['price', 'desc'],
                // Rang fixé par la boutique en glisser-déposer. La date ne
                // départage plus que les produits laissés au même rang.
                default      => ['position', 'asc'],
            })
            ->when(! in_array($request->sort, ['price_asc', 'price_desc'], true),
                fn ($q) => $q->orderBy('created_at', 'desc'))
            ->paginate($request->integer('per_page', 24));

        return ProductResource::collection($products);
    }

    public function show(string $slug): ProductResource
    {
        $query = Product::where('is_active', true)
            ->withAvg(['reviews as reviews_avg_rating' => fn($q) => $q->where('status', 'approved')], 'rating')
            ->withCount(['reviews as reviews_count' => fn($q) => $q->where('status', 'approved')])
            ->with(['images', 'category', 'variants.attributeValues.attribute', 'attributes.values']);

        // Accepte aussi l'ID numérique (ex: panier invité qui fetch /products/10)
        if (is_numeric($slug)) {
            $query->where('id', (int) $slug);
        } else {
            $query->where('slug', $slug);
        }

        $product = $query->firstOrFail();

        return new ProductResource($product);
    }

    /**
     * GET /products/{slug}/related
     * Produits similaires : même catégorie en priorité, puis même gamme,
     * complétés par d'autres produits si besoin. Exclut le produit courant.
     */
    public function related(string $slug): ResourceCollection
    {
        $current = is_numeric($slug)
            ? Product::where('id', (int) $slug)->firstOrFail()
            : Product::where('slug', $slug)->firstOrFail();

        $limit = 4;

        $base = fn() => Product::where('is_active', true)
            ->where('id', '!=', $current->id)
            ->withAvg(['reviews as reviews_avg_rating' => fn($q) => $q->where('status', 'approved')], 'rating')
            ->withCount(['reviews as reviews_count' => fn($q) => $q->where('status', 'approved')])
            ->with(['images', 'category']);

        $price    = (float) $current->price;
        $priceMin = $price * 0.6;   // ±40 % autour du prix courant
        $priceMax = $price * 1.4;

        // 1) Produits similaires (standard e-commerce) :
        //    même catégorie + prix comparable (±40 %). C'est le vrai
        //    "vous aimerez aussi" : pertinence catégorie ET budget.
        $related = collect();
        if ($current->category_id) {
            $related = $base()
                ->where('category_id', $current->category_id)
                ->whereBetween('price', [$priceMin, $priceMax])
                ->inRandomOrder()
                ->limit($limit)
                ->get();
        }

        // 2) Pas (assez) de similaires → on élargit à toute la CATÉGORIE.
        if ($related->count() < $limit && $current->category_id) {
            $related = $related->concat(
                $base()
                    ->whereNotIn('id', $related->pluck('id'))
                    ->where('category_id', $current->category_id)
                    ->inRandomOrder()
                    ->limit($limit - $related->count())
                    ->get()
            );
        }

        // 3) Toujours pas assez → on élargit à la GAMME (product_line).
        if ($related->count() < $limit && $current->product_line_id) {
            $related = $related->concat(
                $base()
                    ->whereNotIn('id', $related->pluck('id'))
                    ->where('product_line_id', $current->product_line_id)
                    ->inRandomOrder()
                    ->limit($limit - $related->count())
                    ->get()
            );
        }

        // 4) Dernier filet : d'autres produits actifs.
        if ($related->count() < $limit) {
            $related = $related->concat(
                $base()
                    ->whereNotIn('id', $related->pluck('id'))
                    ->inRandomOrder()
                    ->limit($limit - $related->count())
                    ->get()
            );
        }

        return ProductResource::collection($related);
    }
}
