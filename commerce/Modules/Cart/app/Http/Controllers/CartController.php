<?php

namespace Modules\Cart\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Cart\Data\CartItemData;
use Modules\Cart\Http\Requests\AddToCartRequest;
use Modules\Cart\Http\Requests\UpdateCartItemRequest;
use Modules\Cart\Services\CartService;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cartService) {}

    public function index(Request $request): JsonResponse
    {
        $cart = $this->cartService->resolve($request);
        $cart->load(['items.product.images', 'items.variant.attributeValues.attribute']);
        return response()->json($this->cartService->summary($cart));
    }

    public function add(AddToCartRequest $request): JsonResponse
    {
        $cart = $this->cartService->resolve($request);
        $data = CartItemData::from($request->validated());

        try {
            $cart = $this->cartService->add($cart, $data);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->cartService->summary($cart));
    }

    public function update(UpdateCartItemRequest $request, int $itemId): JsonResponse
    {
        $cart = $this->cartService->resolve($request);
        $cart = $this->cartService->updateItem($cart, $itemId, $request->integer('quantity'));
        return response()->json($this->cartService->summary($cart));
    }

    public function remove(Request $request, int $itemId): JsonResponse
    {
        $cart = $this->cartService->resolve($request);
        $cart = $this->cartService->removeItem($cart, $itemId);
        return response()->json($this->cartService->summary($cart));
    }

    public function applyCoupon(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string']);
        $cart = $this->cartService->resolve($request);

        try {
            $cart = $this->cartService->applyCoupon($cart, $request->code);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->cartService->summary($cart));
    }

    public function removeCoupon(Request $request): JsonResponse
    {
        $cart = $this->cartService->resolve($request);
        $cart = $this->cartService->removeCoupon($cart);
        return response()->json($this->cartService->summary($cart));
    }

    /**
     * POST /cart/merge  (auth:sanctum)
     * Fusionne les articles du panier invité (localStorage) dans le panier utilisateur.
     */
    public function merge(Request $request): JsonResponse
    {
        $request->validate([
            'items'                  => 'array',
            'items.*.product_id'     => 'required|integer|exists:products,id',
            'items.*.variant_id'     => 'nullable|integer|exists:product_variants,id',
            'items.*.quantity'       => 'required|integer|min:1',
        ]);

        $cart = $this->cartService->resolve($request);

        foreach ($request->input('items', []) as $item) {
            try {
                $this->cartService->add($cart, CartItemData::from($item));
            } catch (\RuntimeException) {
                // Stock insuffisant → article ignoré silencieusement
            }
        }

        $cart->refresh()->load(['items.product.images', 'items.variant.attributeValues.attribute']);
        return response()->json($this->cartService->summary($cart));
    }
}
