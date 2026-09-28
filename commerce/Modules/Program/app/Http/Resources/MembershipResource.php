<?php

namespace Modules\Program\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MembershipResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'user_id'         => $this->user_id,
            'tier'            => $this->tier,
            'points'          => $this->points,
            'points_lifetime' => $this->points_lifetime,
            'orders_count'    => $this->orders_count,
            'created_at'      => $this->created_at?->toISOString(),
            'user'            => $this->whenLoaded('user', fn() => [
                'id'    => $this->user->id,
                'name'  => $this->user->name,
                'email' => $this->user->email,
            ]),
        ];
    }
}
