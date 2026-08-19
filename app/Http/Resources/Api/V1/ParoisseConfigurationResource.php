<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParoisseConfigurationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'nom' => $this->nom,
            'code_paroisse' => $this->code_paroisse,
            'diocese' => $this->diocese,
            'doyenne' => $this->doyenne,
            'ville' => $this->ville,
            'commune' => $this->commune,
            'telephone' => $this->telephone,
            'email' => $this->email,
            'adresse' => $this->adresse,
            'logo_url' => $this->logo_path ? asset('storage/' . $this->logo_path) : null,
            'cure_nom' => $this->cure_nom,
            'coordination_nom' => $this->coordination_nom ?? 'Coordination de la Catéchèse',
            'statut' => $this->statut,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
