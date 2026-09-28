<?php

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'sku'              => $this->sku,
            'price'            => $this->price,
            'compare_price'    => $this->compare_price,
            'stock'            => $this->stock,
            'label'            => $this->label,
            'attribute_values' => $this->whenLoaded('attributeValues', fn() =>
                $this->attributeValues->map(fn($v) => [
                    'attribute' => $v->attribute->name,
                    'value'     => $v->value,
                    'color_hex' => $v->color_hex,
                ])
            ),
            'is_active'        => $this->is_active,
        ];
    }
}
