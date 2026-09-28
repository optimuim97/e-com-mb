<?php

namespace App\Services;

use App\Data\OrderData;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function createFromCart(User $user, OrderData $data): Order
    {
        // Try to load the DB cart first
        $cart = Cart::where('user_id', $user->id)
            ->with(['items.product', 'items.variant'])
            ->first();

        $useCartItems  = $cart && $cart->items->isNotEmpty();
        $usePayloadItems = ! $useCartItems && ! empty($data->items);

        if (! $useCartItems && ! $usePayloadItems) {
            throw new \RuntimeException('Panier vide');
        }

        // Build a normalised item list so the transaction works the same way
        // regardless of source (DB cart vs. payload fallback).
        $resolvedItems = $useCartItems
            ? $this->resolveCartItems($cart->items)
            : $this->resolvePayloadItems($data->items);

        if ($resolvedItems->isEmpty()) {
            throw new \RuntimeException('Panier vide');
        }

        return DB::transaction(function () use ($cart, $user, $data, $resolvedItems, $useCartItems) {
            $subtotal = $resolvedItems->sum(fn($i) => $i['unit_price'] * $i['quantity']);
            $discount = 0;
            $couponCode = $cart?->coupon_code;

            if ($couponCode) {
                $coupon = Coupon::where('code', $couponCode)->first();
                if ($coupon?->isValid($subtotal)) {
                    $discount = $coupon->computeDiscount($subtotal);
                    $coupon->increment('used_count');
                }
            }

            $total = max(0, $subtotal - $discount);

            $order = Order::create([
                'number'           => Order::generateNumber(),
                'user_id'          => $user->id,
                'status'           => 'pending',
                'subtotal'         => $subtotal,
                'shipping_cost'    => 0,
                'discount_amount'  => $discount,
                'total'            => $total,
                'currency'         => 'XOF',
                'shipping_address' => $data->shipping_address->toArray(),
                'billing_address'  => ($data->billing_address ?? $data->shipping_address)->toArray(),
                'shipping_method'  => $data->shipping_method,
                'coupon_code'      => $couponCode,
                'customer_note'    => $data->customer_note,
            ]);

            foreach ($resolvedItems as $item) {
                OrderItem::create([
                    'order_id'      => $order->id,
                    'product_id'    => $item['product_id'],
                    'variant_id'    => $item['variant_id'],
                    'product_name'  => $item['product_name'],
                    'variant_label' => $item['variant_label'],
                    'sku'           => $item['sku'],
                    'quantity'      => $item['quantity'],
                    'unit_price'    => $item['unit_price'],
                    'total_price'   => $item['unit_price'] * $item['quantity'],
                ]);

                if ($item['track_stock']) {
                    if ($item['variant_id']) {
                        ProductVariant::where('id', $item['variant_id'])
                            ->decrement('stock', $item['quantity']);
                    } else {
                        Product::where('id', $item['product_id'])
                            ->decrement('stock', $item['quantity']);
                    }
                }
            }

            // Clear DB cart only if we used it
            if ($useCartItems && $cart) {
                $cart->items()->delete();
                $cart->update(['coupon_code' => null]);
            }

            return $order->load('items');
        });
    }

    /** Normalise DB cart items into a flat array. */
    private function resolveCartItems(Collection $items): Collection
    {
        return $items->map(fn($item) => [
            'product_id'    => $item->product_id,
            'variant_id'    => $item->variant_id,
            'product_name'  => $item->product->name,
            'variant_label' => $item->variant?->label,
            'sku'           => $item->variant?->sku ?? $item->product->sku,
            'quantity'       => $item->quantity,
            'unit_price'    => (float) ($item->variant?->price ?? $item->product->price),
            'track_stock'   => (bool) $item->product->track_stock,
        ]);
    }

    /** Normalise payload items (fallback when DB cart was already cleared). */
    private function resolvePayloadItems(array $payloadItems): Collection
    {
        $productIds = collect($payloadItems)->pluck('product_id')->unique();
        $variantIds = collect($payloadItems)->pluck('variant_id')->filter()->unique();

        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
        $variants = $variantIds->isNotEmpty()
            ? ProductVariant::whereIn('id', $variantIds)->get()->keyBy('id')
            : collect();

        return collect($payloadItems)
            ->filter(fn($i) => $products->has($i['product_id']))
            ->map(function ($i) use ($products, $variants) {
                $product = $products[$i['product_id']];
                $variant = isset($i['variant_id']) ? $variants->get($i['variant_id']) : null;

                return [
                    'product_id'    => $product->id,
                    'variant_id'    => $variant?->id,
                    'product_name'  => $product->name,
                    'variant_label' => $variant?->label,
                    'sku'           => $variant?->sku ?? $product->sku,
                    'quantity'      => (int) $i['quantity'],
                    'unit_price'    => (float) ($variant?->price ?? $product->price),
                    'track_stock'   => (bool) $product->track_stock,
                ];
            })
            ->values();
    }

    public function updateStatus(Order $order, string $status, ?string $trackingNumber = null): Order
    {
        $attrs = ['status' => $status];

        if ($status === 'shipped' && ! $order->shipped_at) {
            $attrs['shipped_at'] = now();
        }
        if ($status === 'delivered' && ! $order->delivered_at) {
            $attrs['delivered_at'] = now();
        }
        if ($trackingNumber) {
            $attrs['tracking_number'] = $trackingNumber;
        }

        $order->update($attrs);
        return $order->fresh(['user', 'items']);
    }
}
