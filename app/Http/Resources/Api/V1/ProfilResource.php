<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfilResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $permissions = $this->permissions ?? [];

        return [
            'id'                       => $this->uuid,
            'uuid'                     => $this->uuid,
            'paroisse_configuration_id' => $this->paroisse_configuration_id,
            'name'                     => $this->nom,
            'nom'                      => $this->nom,
            'code'              => $this->code,
            'description'       => $this->description,
            'statut'            => ucfirst($this->statut ?? 'actif'), // Actif, Inactif
            'statut_code'       => $this->statut ?? 'actif',
            'permissions'       => $permissions,
            'total_permissions' => is_array($permissions) ? count($permissions) : 0,
            'is_system'         => $this->is_system,
            'users_count'       => $this->users_count ?? ($this->users ? $this->users->count() : 0),
            'menus'             => $this->getAccessibleMenusTree(),
            'created_at'        => $this->created_at?->toIso8601String(),
        ];
    }
}
