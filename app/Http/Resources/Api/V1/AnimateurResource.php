<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnimateurResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->uuid,
            'nom'                => $this->nom,
            'prenoms'            => $this->prenoms,
            'nom_complet'        => $this->nom_complet,
            'sexe'               => $this->sexe,
            'telephone'          => $this->telephone,
            'email'              => $this->email,
            'profession'         => $this->profession,
            'statut'             => $this->statut ?? 'actif',
            'dernier_login_at'   => $this->dernier_login_at?->toIso8601String(),
            'affectations_count' => $this->affectations()->count(),
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}
