<?php

namespace Modules\Purchase\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'product_id'       => $this->product_id,
            'variant_id'       => $this->variant_id,
            'quantity'         => $this->quantity,
            'unit_cost'        => $this->unit_cost,
            'total_cost'       => $this->total_cost,
            // Renseigné à la réception seulement : tant que le bon est en
            // préparation, les frais annexes peuvent encore changer.
            'landed_unit_cost' => $this->landed_unit_cost,

            'product' => $this->whenLoaded('product', fn () => $this->product ? [
                'id'           => $this->product->id,
                'name'         => $this->product->name,
                'sku'          => $this->product->sku,
                'average_cost' => $this->product->average_cost,
            ] : null),

            'variant' => $this->whenLoaded('variant', fn () => $this->variant ? [
                'id'    => $this->variant->id,
                'sku'   => $this->variant->sku,
                'label' => $this->variant->label,
            ] : null),
        ];
    }
}
