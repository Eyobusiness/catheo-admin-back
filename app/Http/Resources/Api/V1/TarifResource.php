<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TarifResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'intitule' => $this->intitule,
            'montant' => (float) $this->montant,
            'type_tarif' => $this->type_tarif,
            'annee_catechese' => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'niveau' => new NiveauResource($this->whenLoaded('niveau')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
