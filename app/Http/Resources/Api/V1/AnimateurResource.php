<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnimateurResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'matricule' => $this->matricule,
            'nom' => $this->nom,
            'prenoms' => $this->prenoms,
            'sexe' => $this->sexe,
            'telephone' => $this->telephone,
            'email' => $this->email,
            'profession' => $this->profession,
            'statut' => $this->statut,
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
