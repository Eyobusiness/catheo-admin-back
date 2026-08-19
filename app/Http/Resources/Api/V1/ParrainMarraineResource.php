<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParrainMarraineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->uuid,
            'type'                   => $this->type,
            'nom_prenoms'            => $this->nom_prenoms,
            'telephone'              => $this->telephone,
            'email'                  => $this->email,
            'domicile'               => $this->domicile,
            'paroisse_origine'       => $this->paroisse_origine,
            'representant_nom'       => $this->representant_nom,
            'representant_contact'   => $this->representant_contact,
            'sacrement_confirmation' => (bool) $this->sacrement_confirmation,
            'catechumene'            => new CatechumeneResource($this->whenLoaded('catechumene')),
            'created_at'             => $this->created_at?->toIso8601String(),
        ];
    }
}
