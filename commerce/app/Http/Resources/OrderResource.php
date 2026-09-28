<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'number'           => $this->number,
            'status'           => $this->status,
            'subtotal'         => $this->subtotal,
            'shipping_cost'    => $this->shipping_cost,
            'discount_amount'  => $this->discount_amount,
            'total'            => $this->total,
            'currency'         => $this->currency,
            'coupon_code'      => $this->coupon_code,
            'shipping_address' => $this->shipping_address,
            'shipping_method'  => $this->shipping_method,
            'tracking_number'  => $this->tracking_number,
            'customer_note'    => $this->customer_note,
            'items'            => OrderItemResource::collection($this->whenLoaded('items')),
            'payments'         => $this->whenLoaded('payments'),
            'user'             => $this->whenLoaded('user', fn() => new UserResource($this->user)),
            'paid_at'          => $this->paid_at,
            'shipped_at'       => $this->shipped_at,
            'delivered_at'     => $this->delivered_at,
            'created_at'       => $this->created_at,
        ];
    }
}
