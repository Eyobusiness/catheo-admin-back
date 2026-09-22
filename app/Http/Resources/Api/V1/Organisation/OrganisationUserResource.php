<?php

namespace App\Http\Resources\Api\V1\Organisation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganisationUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->uuid,
            'id_interne'      => $this->id,
            'organisation_id' => $this->organisation_id,
            'paroisse_id'     => $this->paroisse_configuration_id,
            'name'            => $this->name,
            'email'           => $this->email,
            'telephone'       => $this->telephone,
            'user_type'       => $this->user_type,
            'statut'          => $this->statut,
            'profil'          => $this->profil ? [
                'id'          => $this->profil->uuid ?? $this->profil->id,
                'code'        => $this->profil->code,
                'nom'         => $this->profil->nom,
                'permissions' => (array) ($this->profil->permissions ?? []),
            ] : null,
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
