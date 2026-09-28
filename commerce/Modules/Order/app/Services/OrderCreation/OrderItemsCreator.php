<?php

namespace Modules\Order\Services\OrderCreation;

use Modules\Order\Models\Order;
use Modules\Cart\Models\Cart;
use Modules\Order\Models\OrderItem;
use Modules\Promo\Support\SellingPrice;

class OrderItemsCreator
{
    public function createFromCartItems(Order $order, Cart $cart): void
    {
        foreach ($cart->items as $item) {
            // Prix, opération appliquée et remise consentie viennent du même
            // calcul : les dissocier ferait écrire une attribution qui ne
            // correspond pas au montant facturé.
            $vente = SellingPrice::resolve($item->product, $item->variant);

            OrderItem::create([
                'order_id'        => $order->id,
                'product_id'      => $item->product_id,
                'variant_id'      => $item->variant_id,
                'promotion_id'    => $vente['promotion_id'],
                'product_name'    => $item->product->name,
                'variant_label'   => $item->variant?->label,
                'sku'             => $item->variant?->sku ?? $item->product->sku,
                'quantity'        => $item->quantity,
                'unit_price'      => $vente['price'],
                'discount_amount' => $vente['discount'] * $item->quantity,
                'total_price'     => $vente['price'] * $item->quantity,
            ]);
        }
    }

    /**
     * Le prix réellement pratiqué, promotions comprises.
     *
     * La ligne lisait `products.price` alors que l'en-tête de commande reprend
     * le sous-total du panier, lui calculé sur le prix effectif : la somme des
     * lignes ne retombait pas sur le total, et la facture affichait le tarif
     * plein d'une commande remisée.
     */
    private function getUnitPrice($item): float
    {
        return SellingPrice::for($item->product, $item->variant);
    }
}
