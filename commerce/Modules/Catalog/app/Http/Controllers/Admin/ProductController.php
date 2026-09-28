<?php

namespace Modules\Catalog\Http\Controllers\Admin;

use Modules\Catalog\Data\ProductData;
use App\Http\Controllers\Controller;
use App\Support\ResourcePermissions;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\Catalog\Http\Requests\StoreProductRequest;
use Modules\Catalog\Http\Requests\UpdateProductRequest;
use Modules\Catalog\Http\Resources\ProductResource;
use Modules\Catalog\Http\Resources\ProductImageResource;
use Modules\Catalog\Models\Product;
use Modules\Order\Support\StockReservation;
use Modules\Catalog\Models\ProductImage;
use Modules\Catalog\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ResourcePermissions::crud('products',
            extra: [
                'products.create' => ['uploadImage'],
                'products.edit'   => ['setCover'],
                'products.delete' => ['deleteImage'],
            ],
            verbs: ['view' => ['index', 'show', 'editShow']],
        );
    }

    public function __construct(private readonly ProductService $productService) {}

    public function index(Request $request): ResourceCollection
    {
        return ProductResource::collection(
            Product::with(['category', 'categories', 'images', 'productLine'])
                // Réservé : ce qui est vendu mais encore en boutique. Agrégé
                // ici plutôt que par produit — une requête par ligne rendrait
                // le listing inutilisable passé quelques dizaines d'articles.
                ->withSum([
                    'orderItems as reserved_quantity' => fn ($q) => $q->whereHas(
                        'order',
                        fn ($o) => $o->whereIn('status', StockReservation::HOLDING_STATUSES)
                    ),
                ], 'quantity')
                ->when($request->filled('search'), fn($q) => $q->where('name', 'like', '%'.$request->search.'%'))
                // Filtres via pivots : couvrent les produits multi-catégories / multi-gammes
                ->when($request->filled('category_id'), fn($q) =>
                    $q->whereHas('categories', fn($c) => $c->where('categories.id', $request->category_id)))
                ->when($request->filled('product_line_id'), fn($q) =>
                    $q->whereHas('productLines', fn($l) => $l->where('product_lines.id', $request->product_line_id)))
                // Même ordre que la vitrine : ce que l'administration déplace
                // ici, c'est ce que la cliente verra. Trier autrement rendrait
                // le glisser-déposer incompréhensible.
                ->orderBy('position')
                ->latest()
                ->paginate(20)
        );
    }

    /**
     * Synchronise les catégories multiples (pivot) et maintient
     * `category_id` = catégorie principale (première sélectionnée).
     */
    private function syncCategories(Product $product, Request $request): void
    {
        if (! $request->has('category_ids')) {
            // Compat : requête sans multi-sélection → pivot aligné sur category_id
            $product->categories()->sync(array_filter([$product->category_id]));
            return;
        }

        $ids = array_values(array_unique(array_map('intval', $request->input('category_ids', []))));
        $product->categories()->sync($ids);
        $product->update(['category_id' => $ids[0] ?? null]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data    = ProductData::fromRequest($request->validated());
        $product = $this->productService->create($data);
        $this->syncCategories($product, $request);
        return response()->json(new ProductResource($product->load(['category', 'categories', 'images'])), 201);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource(
            $product->load(['category', 'images', 'variants.attributeValues.attribute', 'attributes.values'])
        );
    }

    public function editShow(int $id) : ProductResource
    {
        $product = Product::findOrFail($id);
        return new ProductResource(
            $product->load(['category', 'categories', 'images', 'variants.attributeValues.attribute', 'attributes.values'])
        );
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $data    = ProductData::fromRequest(array_merge($product->toArray(), $request->validated()));
        $product = $this->productService->update($product, $data);
        $this->syncCategories($product, $request);
        return new ProductResource($product->load(['category', 'categories', 'images']));
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->productService->delete($product);
        return response()->json(['message' => 'Produit archivé']);
    }

    /**
     * PUT /admin/products/reorder
     * Range les produits dans l'ordre reçu (glisser-déposer de la liste).
     *
     * On n'accepte que la page affichée, pas le catalogue entier : la liste
     * est paginée, et renvoyer tout obligerait l'écran à charger des milliers
     * d'identifiants pour déplacer une vignette.
     *
     * Le rang de départ est celui du premier produit de la page. Les articles
     * des autres pages gardent le leur : déplacer une vignette en page 2 ne
     * doit pas renuméroter la page 1.
     */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:products,id'],
        ]);

        $ids = $data['ids'];

        // Rangs actuellement occupés par ces produits : on les redistribue
        // dans le nouvel ordre plutôt que de repartir de zéro, pour ne pas
        // déplacer le bloc par rapport au reste du catalogue.
        $rangs = Product::whereIn('id', $ids)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('position')
            ->all();

        // Rangs indistincts — c'est le cas d'un catalogue jamais rangé, où
        // tout vaut zéro. Les redistribuer tels quels réécrirait zéro partout :
        // l'appel répondrait 200 sans rien changer, et l'écran afficherait un
        // ordre que la vitrine ignore.
        if (count(array_unique($rangs)) < count($ids)) {
            $base  = $rangs === [] ? 1 : max(1, min($rangs));
            $rangs = range($base, $base + count($ids) - 1);
        }

        DB::transaction(function () use ($ids, $rangs) {
            foreach ($ids as $index => $id) {
                Product::where('id', $id)->update([
                    'position' => $rangs[$index] ?? $index,
                ]);
            }
        });

        return response()->json(['message' => 'Ordre enregistré.']);
    }

    public function uploadImage(Request $request, Product $product): JsonResponse
    {
        $request->validate(['image' => 'required|image|max:5120', 'alt' => 'nullable|string']);
        $path  = $request->file('image')->store('products', 'public');
        $count = $product->images()->count();
        $image = ProductImage::create([
            'product_id' => $product->id,
            'path'       => $path,
            'alt'        => $request->input('alt', $product->name),
            'sort_order' => $count,
            'is_cover'   => $count === 0,
        ]);
        return response()->json(new ProductImageResource($image), 201);
    }

    public function setCover(Product $product, ProductImage $image): JsonResponse
    {
        abort_unless($image->product_id === $product->id, 403);
        $product->images()->update(['is_cover' => false]);
        $image->update(['is_cover' => true]);
        return response()->json(['message' => 'Image de couverture mise à jour']);
    }

    public function deleteImage(Product $product, ProductImage $image): JsonResponse
    {
        abort_unless($image->product_id === $product->id, 403);
        \Storage::disk('public')->delete($image->path);
        $image->delete();
        if ($image->is_cover) {
            $first = $product->images()->orderBy('sort_order')->first();
            $first?->update(['is_cover' => true]);
        }
        return response()->json(['message' => 'Image supprimée']);
    }
}
