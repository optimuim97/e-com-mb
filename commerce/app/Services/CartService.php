<?php

namespace App\Services;

use App\Data\CartItemData;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function resolve(Request $request): Cart
    {
        if ($user = $request->user()) {
            return Cart::firstOrCreate(['user_id' => $user->id]);
        }
        return Cart::firstOrCreate(['session_id' => session()->getId()]);
    }

    public function add(Cart $cart, CartItemData $data): Cart
    {
        $product = Product::findOrFail($data->product_id);
        $variant = $data->variant_id ? ProductVariant::findOrFail($data->variant_id) : null;

        $stock = $variant?->stock ?? $product->stock;
        if ($product->track_stock && $stock < $data->quantity) {
            throw new \RuntimeException('Stock insuffisant');
        }

        $existing = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $data->product_id)
            ->where('variant_id', $data->variant_id)
            ->first();

        if ($existing) {
            $existing->increment('quantity', $data->quantity);
        } else {
            CartItem::create([
                'cart_id'    => $cart->id,
                'product_id' => $data->product_id,
                'variant_id' => $data->variant_id,
                'quantity'   => $data->quantity,
            ]);
        }

        return $cart->fresh(['items.product.images', 'items.variant.attributeValues.attribute']);
    }

    public function updateItem(Cart $cart, int $itemId, int $quantity): Cart
    {
        $item = CartItem::where('cart_id', $cart->id)->findOrFail($itemId);
        $quantity === 0 ? $item->delete() : $item->update(['quantity' => $quantity]);
        return $cart->fresh(['items.product.images', 'items.variant.attributeValues.attribute']);
    }

    public function removeItem(Cart $cart, int $itemId): Cart
    {
        CartItem::where('cart_id', $cart->id)->findOrFail($itemId)->delete();
        return $cart->fresh(['items.product.images', 'items.variant.attributeValues.attribute']);
    }

    public function applyCoupon(Cart $cart, string $code): Cart
    {
        $coupon = Coupon::where('code', strtoupper($code))->firstOrFail();
        if (! $coupon->isValid($cart->subtotal)) {
            throw new \RuntimeException('Code promo invalide ou expiré');
        }
        $cart->update(['coupon_code' => $coupon->code]);
        return $cart->fresh(['items.product.images', 'items.variant.attributeValues.attribute']);
    }

    public function removeCoupon(Cart $cart): Cart
    {
        $cart->update(['coupon_code' => null]);
        return $cart->fresh(['items.product.images', 'items.variant.attributeValues.attribute']);
    }

    public function summary(Cart $cart): array
    {
        $subtotal = $cart->subtotal;
        $discount = 0;
        $coupon   = null;

        if ($cart->coupon_code) {
            $c = Coupon::where('code', $cart->coupon_code)->first();
            if ($c && $c->isValid($subtotal)) {
                $discount = $c->computeDiscount($subtotal);
                $coupon   = ['code' => $c->code, 'type' => $c->type, 'value' => $c->value, 'discount' => $discount];
            }
        }

        return [
            'id'         => $cart->id,
            'items'      => $cart->items,
            'subtotal'   => $subtotal,
            'discount'   => $discount,
            'coupon'     => $coupon,
            'total'      => max(0, $subtotal - $discount),
            'item_count' => $cart->items->sum('quantity'),
        ];
    }
}
