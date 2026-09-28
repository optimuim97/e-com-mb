<?php

namespace Modules\Program\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BenefitResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'tier'        => $this->tier,
            'icon'        => $this->icon,
            'title'       => $this->title,
            'description' => $this->description,
            'is_active'   => $this->is_active,
            'sort_order'  => $this->sort_order,
        ];
    }
}
