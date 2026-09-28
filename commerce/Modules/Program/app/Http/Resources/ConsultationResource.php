<?php

namespace Modules\Program\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ConsultationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'type'         => $this->type,
            'channel'      => $this->channel,
            'phone'        => $this->phone,
            'preferred_at' => $this->preferred_at?->toISOString(),
            'message'      => $this->message,
            'status'       => $this->status,
            'advisor_note' => $this->advisor_note,
            'created_at'   => $this->created_at?->toISOString(),
            'user'         => $this->whenLoaded('user', fn() => [
                'id'    => $this->user->id,
                'name'  => $this->user->name,
                'email' => $this->user->email,
            ]),
        ];
    }
}
