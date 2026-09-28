<?php

namespace Modules\Purchase\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'contact_name' => $this->contact_name,
            'phone'        => $this->phone,
            'email'        => $this->email,
            'address'      => $this->address,
            'notes'        => $this->notes,
            'is_active'    => $this->is_active,
            'orders_count' => $this->whenCounted('purchaseOrders'),
            'created_at'   => $this->created_at,
        ];
    }
}
