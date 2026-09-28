<?php

namespace Modules\Customer\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isGenerated = str_ends_with($this->email ?? '', '@clients.local');

        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'email'               => $isGenerated ? null : $this->email,   // Ne jamais exposer l'email généré
            'username'            => $this->username,
            'phone'               => $this->phone,
            'has_pin'             => (bool) $this->order_pin,
            'is_generated_email'  => $isGenerated,                         // true = compte commande rapide
            'roles'               => $this->whenLoaded('roles', fn() => $this->roles->pluck('name')),
            // Accès admin : n'expose les flags/permissions QUE lorsque la relation
            // roles est déjà chargée (auth : /me, login, register) — évite tout
            // N+1 dans les listes où l'utilisateur est un simple client.
            'is_admin'            => $this->when($this->relationLoaded('roles'), fn() => $this->hasRole('admin')),
            'is_staff'            => $this->when($this->relationLoaded('roles'), fn() => $this->hasAnyRole(['admin', 'manager', 'staff'])),
            'permissions'         => $this->when($this->relationLoaded('roles'), fn() => $this->getAllPermissions()->pluck('name')->values()),
            // Préfixes de permission des modules éteints. Le front s'en sert
            // pour retirer du menu les écrans devenus inutiles : une entrée
            // qui ne mène qu'à un 403 est pire que pas d'entrée du tout.
            'disabled_modules'    => $this->when(
                $this->relationLoaded('roles'),
                fn() => \App\Support\ModuleRegistry::disabledPermissionPrefixes(),
            ),
        ];
    }
}
