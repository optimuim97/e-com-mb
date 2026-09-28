<?php

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'url'        => $this->url,
            'alt'        => $this->alt,
            'is_cover'   => $this->is_cover,
            'sort_order' => $this->sort_order,
        ];
    }
}
