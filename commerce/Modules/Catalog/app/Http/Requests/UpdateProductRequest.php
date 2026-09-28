<?php

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('product')?->id;

        return [
            'name'              => 'sometimes|string|max:255',
            'description'       => 'nullable|string',
            'short_description' => 'nullable|string',
            'price'             => 'sometimes|numeric|min:0',
            'compare_price'     => 'nullable|numeric|min:0',
            'cost_price'        => 'nullable|numeric|min:0',
            'sku'               => "nullable|string|unique:products,sku,{$id}",
            'stock'             => 'sometimes|integer|min:0',
            'type'              => 'sometimes|in:physical,digital,service,subscription',
            'category_id'       => 'nullable|exists:categories,id',
            'category_ids'      => 'nullable|array',
            'category_ids.*'    => 'integer|exists:categories,id',
            'product_line_id'   => 'nullable|exists:product_lines,id',
            'is_active'         => 'boolean',
            'is_featured'       => 'boolean',
            'track_stock'       => 'boolean',
            'weight'            => 'nullable|numeric|min:0',
        ];
    }
}
