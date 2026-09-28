<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'product_name'  => $this->product_name,
            'variant_label' => $this->variant_label,
            'sku'           => $this->sku,
            'quantity'      => $this->quantity,
            'unit_price'    => $this->unit_price,
            'total_price'   => $this->total_price,
        ];
    }
}
