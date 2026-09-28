<?php

namespace Modules\Order\Services\OrderCreation;

use Modules\Cart\Models\Cart;
use App\Models\User;

class CartValidator
{
    /**
     * Return the user's cart with items loaded, or null if the cart is empty
     * or does not exist. The caller decides whether to fall back to payload items.
     */
    public function validateAndGetCart(User $user): ?Cart
    {
        $cart = Cart::where('user_id', $user->id)
            ->with(['items.product', 'items.variant'])
            ->first();

        if (! $cart || $cart->items->isEmpty()) {
            return null;
        }

        return $cart;
    }
}
