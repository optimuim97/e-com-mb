<?php

namespace Modules\Cart\Services;

use Modules\Cart\Data\CartItemData;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Coupon\Models\Coupon;
use Modules\Coupon\Support\CouponDiscount;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Catalog\Support\WeeklyAllocationGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function resolve(Request $request): Cart
    {
        if ($user = $request->user('sanctum')) {
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

        // Quota de la semaine, vérifié dès l'ajout : c'est ici, sur la fiche,
        // que la cliente doit l'apprendre, pas après avoir saisi son adresse.
        // Tout le panier compte, déclinaisons comprises — l'allocation porte
        // sur l'article, et cinq ajouts d'un pot ne doivent pas en contourner
        // un plafond de trois.
        $dejaAuPanier = (int) CartItem::where('cart_id', $cart->id)
            ->where('product_id', $data->product_id)
            ->sum('quantity');

        $refus = app(WeeklyAllocationGuard::class)->rejection(
            $product->id,
            $product->name,
            (int) $data->quantity,
            $dejaAuPanier,
        );

        if ($refus !== null) {
            throw new \RuntimeException($refus);
        }

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

        // Reset abandonment state — user is actively shopping again
        if ($cart->abandoned_at) {
            $cart->update([
                'abandoned_at'       => null,
                'reminder_1_sent_at' => null,
                'reminder_3_sent_at' => null,
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

        // Un code ciblé accepté sur un panier qui ne contient aucun de ses
        // articles s'afficherait « appliqué » pour une remise nulle. Autant le
        // dire tout de suite, et nommer les articles concernés.
        if (CouponDiscount::forCart($coupon, $cart)['discount'] <= 0) {
            throw new \RuntimeException($this->raisonNonApplicable($coupon));
        }

        $cart->update(['coupon_code' => $coupon->code]);
        return $cart->fresh(['items.product.images', 'items.variant.attributeValues.attribute']);
    }

    /** Pourquoi le code n'accorde rien sur ce panier. */
    private function raisonNonApplicable(Coupon $coupon): string
    {
        $articles = $coupon->products()->pluck('products.name');

        if ($articles->isNotEmpty()) {
            return "Ce code promo ne s'applique qu'à : " . $articles->implode(', ') . '.';
        }

        return "Ce code promo ne s'applique à aucun article de votre panier : "
            . 'les articles déjà en promotion en sont exclus.';
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
                // Même règle qu'à la facturation : la remise affichée ici était
                // calculée sur tout le sous-total, donc plus forte que celle
                // réellement accordée dès qu'un article était déjà en promotion.
                $discount = CouponDiscount::forCart($c, $cart)['discount'];
                $coupon   = [
                    'code'     => $c->code,
                    'type'     => $c->type,
                    'value'    => $c->value,
                    'discount' => $discount,
                    // Sert à expliquer une remise plus faible qu'attendu.
                    'products' => $c->products()->pluck('products.name')->all(),
                ];
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
