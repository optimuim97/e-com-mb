<?php

namespace Modules\Program\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TierResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'slug'         => $this->slug,
            'name'         => $this->name,
            'icon'         => $this->icon,
            'color'        => $this->color,
            'points_min'   => $this->points_min,
            'points_max'   => $this->points_max,
            'discount_pct' => $this->discount_pct,
            'sort_order'   => $this->sort_order,
            'benefits'     => $this->whenLoaded('benefits', fn() =>
                $this->benefits->pluck('title')
            ),
        ];
    }
}
