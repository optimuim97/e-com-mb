<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }

    public function rules(): array
    {
        return [
            'shipping_address'               => 'required|array',
            'shipping_address.first_name'    => 'required|string',
            'shipping_address.last_name'     => 'nullable|string',
            'shipping_address.address_line1' => 'required|string',
            'shipping_address.city'          => 'required|string',
            'shipping_address.country'       => 'required|string|min:2|max:5',
            'shipping_address.phone'         => 'nullable|string',
            'billing_address'                => 'nullable|array',
            'shipping_method'                => 'nullable|string',
            'payment_method'                 => 'required|in:wave,orange_money,cinetpay,cod',
            'customer_note'                  => 'nullable|string|max:1000',
            // Fallback items from frontend cart (used when DB cart is empty)
            'items'                          => 'nullable|array',
            'items.*.product_id'             => 'required_with:items|integer|exists:products,id',
            'items.*.variant_id'             => 'nullable|integer|exists:product_variants,id',
            'items.*.quantity'               => 'required_with:items|integer|min:1',
        ];
    }
}
