<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'short_description' => 'nullable|string',
            'price'             => 'required|numeric|min:0',
            'compare_price'     => 'nullable|numeric|min:0',
            'cost_price'        => 'nullable|numeric|min:0',
            'sku'               => 'nullable|string|unique:products',
            'stock'             => 'required|integer|min:0',
            'type'              => 'required|in:physical,digital,service,subscription',
            'category_id'       => 'nullable|exists:categories,id',
            'is_active'         => 'boolean',
            'is_featured'       => 'boolean',
            'track_stock'       => 'boolean',
            'weight'            => 'nullable|numeric|min:0',
        ];
    }
}
