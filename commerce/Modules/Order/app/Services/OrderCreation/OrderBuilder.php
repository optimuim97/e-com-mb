<?php

namespace Modules\Order\Services\OrderCreation;

use App\Models\User;
use Modules\Cart\Models\Cart;
use Modules\Order\Data\OrderData;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;

class OrderBuilder
{
    public function __construct(
        private readonly ShippingResolver $shippingResolver,
    ) {}

    public function buildOrder(User $user, Cart $cart, OrderData $data, array $discountInfo): Order
    {
        $subtotal = (float) $cart->subtotal;
        $discount = (float) $discountInfo['discount'];

        // Frais de livraison résolus CÔTÉ SERVEUR depuis la zone de livraison
        // (source de vérité). Le shipping_cost envoyé par le front est ignoré.
        // Zone introuvable → 0 + marqueur shipping_unknown : les agents
        // renseignent les frais manuellement depuis l'admin.
        $shipping     = $this->shippingResolver->resolve($data->shipping_address->toArray());
        $shippingCost = $shipping['cost'];

        $total = max(0, $subtotal - $discount + $shippingCost);

        $metadata = [];
        if ($shipping['unknown']) {
            $metadata['shipping_unknown'] = true;
        }
        if ($shipping['zone_name']) {
            $metadata['shipping_zone'] = $shipping['zone_name'];
        }

        $order = Order::create([
            'number'           => Order::generateNumber(),
            'user_id'          => $user->id,
            'status'           => 'pending',
            'subtotal'         => $subtotal,
            'shipping_cost'    => $shippingCost,
            'discount_amount'  => $discount,
            'total'            => $total,
            'currency'         => 'XOF',
            'shipping_address' => $data->shipping_address->toArray(),
            'billing_address'  => ($data->billing_address ?? $data->shipping_address)->toArray(),
            'shipping_method'  => $data->shipping_method,
            'payment_method'   => $data->payment_method,
            'coupon_code'      => $cart->coupon_code,
            'customer_note'    => $data->customer_note,
            'metadata'         => $metadata ?: null,
        ]);

        // Enregistrement de paiement (pending jusqu'à confirmation) — seulement
        // si un moyen de règlement est connu. Hors zone il ne l'est pas encore :
        // `payments.provider` étant NOT NULL, la commande échouait en base et la
        // cliente ne pouvait pas finaliser. La ligne sera créée quand l'agent
        // aura fixé les frais puis le mode de règlement.
        if ($data->payment_method) {
            Payment::create([
                'order_id'   => $order->id,
                'provider'   => $data->payment_method,
                'status'     => 'pending',
                'amount'     => $total,
                'currency'   => 'XOF',
            ]);
        }

        return $order;
    }
}
