<?php

namespace App\Http\Controllers\Api\Admin;

use App\Data\ProductData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreProductRequest;
use App\Http\Requests\Catalog\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $productService) {}

    public function index(Request $request): ResourceCollection
    {
        return ProductResource::collection(
            Product::with(['category', 'images'])
                ->when($request->filled('search'), fn($q) => $q->where('name', 'like', '%'.$request->search.'%'))
                ->when($request->filled('category_id'), fn($q) => $q->where('category_id', $request->category_id))
                ->latest()
                ->paginate(20)
        );
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data    = ProductData::fromRequest($request->validated());
        $product = $this->productService->create($data);
        return response()->json(new ProductResource($product->load(['category', 'images'])), 201);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource(
            $product->load(['category', 'images', 'variants.attributeValues.attribute', 'attributes.values'])
        );
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $data    = ProductData::fromRequest(array_merge($product->toArray(), $request->validated()));
        $product = $this->productService->update($product, $data);
        return new ProductResource($product->load(['category', 'images']));
    }

    public function destroy(Product $product): JsonResponse
    {
        // Interdit si le produit est lié à des commandes actives (pending / processing / shipped)
        $activeOrderCount = OrderItem::where('product_id', $product->id)
            ->whereHas('order', fn($q) => $q->whereIn('status', ['pending', 'processing', 'shipped']))
            ->count();

        if ($activeOrderCount > 0) {
            return response()->json([
                'message' => "Impossible de supprimer ce produit : il est présent dans {$activeOrderCount} commande(s) en cours.",
                'active_orders' => $activeOrderCount,
            ], 422);
        }

        $this->productService->delete($product);
        return response()->json(['message' => 'Produit archivé']);
    }

    public function uploadImage(Request $request, Product $product): JsonResponse
    {
        $request->validate(['image' => 'required|image|max:5120', 'alt' => 'nullable|string']);
        $path  = $request->file('image')->store('products', 'public');
        $image = ProductImage::create([
            'product_id' => $product->id,
            'path'       => $path,
            'alt'        => $request->input('alt', $product->name),
            'sort_order' => $product->images()->count(),
            'is_cover'   => $product->images()->count() === 0,
        ]);
        return response()->json($image, 201);
    }

    public function deleteImage(Product $product, ProductImage $image): JsonResponse
    {
        \Storage::disk('public')->delete($image->path);
        $image->delete();
        return response()->json(['message' => 'Image supprimée']);
    }
}
