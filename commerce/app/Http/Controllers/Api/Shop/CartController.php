<?php

namespace App\Http\Controllers\Api\Shop;

use App\Data\CartItemData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddToCartRequest;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $cart = $this->cartService->add($cart, $data);
        return response()->json($this->cartService->summary($cart));
    }

    public function update(Request $request, int $itemId): JsonResponse
    {
        $request->validate(['quantity' => 'required|integer|min:0']);
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
}
