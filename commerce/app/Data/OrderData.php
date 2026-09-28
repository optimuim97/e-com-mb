<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class OrderData extends Data
{
    public function __construct(
        public readonly AddressData  $shipping_address,
        public readonly string       $payment_method,
        public readonly ?AddressData $billing_address = null,
        public readonly ?string      $shipping_method = null,
        public readonly ?string      $customer_note   = null,
        /** @var array<array{product_id:int,variant_id:int|null,quantity:int}>|null */
        public readonly ?array       $items           = null,
    ) {}

    public static function fromValidated(array $validated): self
    {
        return new self(
            shipping_address: AddressData::from($validated['shipping_address']),
            payment_method:   $validated['payment_method'],
            billing_address:  isset($validated['billing_address'])
                ? AddressData::from($validated['billing_address'])
                : null,
            shipping_method:  $validated['shipping_method'] ?? null,
            customer_note:    $validated['customer_note'] ?? null,
            items:            $validated['items'] ?? null,
        );
    }
}
