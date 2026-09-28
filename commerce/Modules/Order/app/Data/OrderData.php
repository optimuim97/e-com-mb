<?php

namespace Modules\Order\Data;

use Spatie\LaravelData\Data;

class OrderData extends Data
{
    public function __construct(
        public readonly AddressData  $shipping_address,
        // Null hors zone : la cliente ne choisit pas un règlement dont le
        // montant n'est pas encore fixé — nos agents le définissent ensuite.
        public readonly ?string      $payment_method,
        public readonly ?AddressData $billing_address = null,
        public readonly ?string      $shipping_method = null,
        public readonly ?string      $customer_note   = null,
        /** @var array<array{product_id:int,variant_id:int|null,quantity:int}>|null */
        public readonly ?array       $items           = null,
        public readonly ?float       $shipping_cost   = null,
        public readonly bool         $shipping_unknown = false,
        // Code saisi hors panier en base — commande rapide. Le panier en base,
        // lui, porte le sien dans `carts.coupon_code`.
        public readonly ?string      $coupon_code     = null,
        // Set once the customer has been shown a similar order still on its
        // way and answered that this one is new. See DuplicateOrderCheck.
        public readonly bool         $confirm_duplicate = false,
    ) {}

    public static function fromValidated(array $validated): self
    {
        return new self(
            shipping_address: AddressData::from($validated['shipping_address']),
            payment_method:   $validated['payment_method'] ?? null,
            billing_address:  isset($validated['billing_address'])
                ? AddressData::from($validated['billing_address'])
                : null,
            shipping_method:  $validated['shipping_method'] ?? null,
            customer_note:    $validated['customer_note'] ?? null,
            items:            $validated['items'] ?? null,
            shipping_cost:    isset($validated['shipping_cost']) ? (float) $validated['shipping_cost'] : null,
            shipping_unknown: (bool) ($validated['shipping_unknown'] ?? false),
            coupon_code:      $validated['coupon_code'] ?? null,
            confirm_duplicate: (bool) ($validated['confirm_duplicate'] ?? false),
        );
    }
}
