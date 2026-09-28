<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class CartItemData extends Data
{
    public function __construct(
        public readonly int  $product_id,
        public readonly int  $quantity,
        public readonly ?int $variant_id = null,
    ) {}
}
