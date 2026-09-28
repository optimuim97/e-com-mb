<?php

namespace Modules\Purchase\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'number'        => $this->number,
            'status'        => $this->status,
            'status_label'  => $this->status_label,
            'editable'      => $this->isEditable(),
            'currency'      => $this->currency,

            'items_total'   => $this->items_total,
            'shipping_cost' => $this->shipping_cost,
            'other_costs'   => $this->other_costs,
            'total'         => $this->total,

            'ordered_at'    => $this->ordered_at,
            'received_at'   => $this->received_at,
            'notes'         => $this->notes,

            'supplier'      => $this->whenLoaded('supplier', fn () => $this->supplier ? [
                'id'   => $this->supplier->id,
                'name' => $this->supplier->name,
            ] : null),

            'items'         => PurchaseOrderItemResource::collection($this->whenLoaded('items')),

            'created_at'    => $this->created_at,
        ];
    }
}
