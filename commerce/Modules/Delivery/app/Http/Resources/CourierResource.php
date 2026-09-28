<?php

namespace Modules\Delivery\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name ?? $this->name,
            'last_name' => $this->last_name ?? '',
            'full_name' => trim(($this->first_name ?? $this->name) . ' ' . ($this->last_name ?? '')),
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'biometric_enabled' => (bool) $this->biometric_enabled,
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->pluck('name');
            }),
            'created_at' => $this->created_at?->toISOString(),
            'last_login_at' => $this->last_login_at?->toISOString(),
        ];
    }
}
