<?php

namespace Modules\Catalog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ResourcePermissions;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\Catalog\Http\Resources\ProductLineResource;
use Modules\Catalog\Models\ProductLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Str;

class ProductLineController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ResourcePermissions::crud('product_lines',
            extra: ['product_lines.edit' => ['syncProducts', 'uploadCover']],
            verbs: ['view' => ['index', 'show', 'listProducts']],
        );
    }

    public function index(Request $request): ResourceCollection
    {
        $lines = ProductLine::withCount('products')
            ->when($request->filled('search'), fn($q) => $q->where('name', 'like', '%'.$request->search.'%'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20);

        return ProductLineResource::collection($lines);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'tagline'     => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'color_hex'   => 'nullable|string|max:7',
            'is_active'   => 'boolean',
            'sort_order'  => 'integer|min:0',
        ]);

        $data['slug'] = $this->uniqueSlug($data['name']);

        $line = ProductLine::create($data);

        if ($request->hasFile('cover_image')) {
            $path = $request->file('cover_image')->store('product-lines', 'public');
            $line->update(['cover_image' => $path]);
        }

        return response()->json(new ProductLineResource($line), 201);
    }

    public function show(ProductLine $productLine): ProductLineResource
    {
        return new ProductLineResource($productLine->loadCount('products'));
    }

    public function update(Request $request, ProductLine $productLine): ProductLineResource
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'tagline'     => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'color_hex'   => 'nullable|string|max:7',
            'is_active'   => 'boolean',
            'sort_order'  => 'integer|min:0',
        ]);

        if (isset($data['name']) && $data['name'] !== $productLine->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $productLine->id);
        }

        if ($request->hasFile('cover_image')) {
            if ($productLine->cover_image) {
                \Storage::disk('public')->delete($productLine->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')->store('product-lines', 'public');
        }

        $productLine->update($data);

        return new ProductLineResource($productLine->loadCount('products'));
    }

    /**
     * Sync the product composition of a product line.
     * Receives { product_ids: [1, 2, 3] }
     * → detaches all products previously in the line, attaches the new ones.
     */
    public function syncProducts(Request $request, ProductLine $productLine): JsonResponse
    {
        $request->validate([
            'product_ids'   => 'required|array',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        $ids = $request->product_ids ?? [];

        // Pivot many-to-many : ne touche QUE la composition de CETTE gamme.
        // (L'ancienne implémentation écrasait products.product_line_id et
        // retirait donc le produit de toute autre gamme.)
        $productLine->products()->sync($ids);

        // Compat colonne legacy product_line_id (« gamme principale ») :
        // - produits ajoutés sans gamme principale → cette gamme
        // - produits retirés dont la principale était cette gamme → une autre
        //   de leurs gammes (ou null)
        if (!empty($ids)) {
            \Modules\Catalog\Models\Product::whereIn('id', $ids)
                ->whereNull('product_line_id')
                ->update(['product_line_id' => $productLine->id]);
        }
        \Modules\Catalog\Models\Product::where('product_line_id', $productLine->id)
            ->whereNotIn('id', $ids ?: [0])
            ->get()
            ->each(function ($p) use ($productLine) {
                $p->update([
                    'product_line_id' => $p->productLines()
                        ->where('product_lines.id', '!=', $productLine->id)
                        ->value('product_lines.id'),
                ]);
            });

        return response()->json([
            'message'       => 'Composition mise à jour.',
            'products_count' => $productLine->products()->where('is_active', true)->count(),
        ]);
    }

    /**
     * List products currently in this line (for admin form).
     */
    public function listProducts(ProductLine $productLine): JsonResponse
    {
        // Colonnes qualifiées : la relation pivot joint product_line_product
        // qui possède aussi un `id` (ambiguïté SQL sinon).
        $products = $productLine->products()
            ->select('products.id', 'products.name', 'products.slug', 'products.price', 'products.sku', 'products.is_active')
            ->orderBy('products.name')
            ->get();

        return response()->json($products);
    }

    public function destroy(ProductLine $productLine): JsonResponse
    {
        // Détacher les produits (pivot) et nettoyer la gamme principale legacy
        \Modules\Catalog\Models\Product::where('product_line_id', $productLine->id)
            ->update(['product_line_id' => null]);
        $productLine->products()->detach();

        if ($productLine->cover_image) {
            \Storage::disk('public')->delete($productLine->cover_image);
        }

        $productLine->delete();

        return response()->json(['message' => 'Gamme supprimée']);
    }

    public function uploadCover(Request $request, ProductLine $productLine): JsonResponse
    {
        $request->validate(['cover_image' => 'required|image|max:5120']);

        if ($productLine->cover_image) {
            \Storage::disk('public')->delete($productLine->cover_image);
        }

        $path = $request->file('cover_image')->store('product-lines', 'public');
        $productLine->update(['cover_image' => $path]);

        return response()->json(['cover_url' => $productLine->fresh()->cover_url]);
    }

    private function uniqueSlug(string $name, ?int $excludeId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;
        while (
            ProductLine::where('slug', $slug)
                ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
