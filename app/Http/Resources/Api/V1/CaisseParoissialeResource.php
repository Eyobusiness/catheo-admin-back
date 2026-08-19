<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaisseParoissialeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'type_mouvement' => $this->type_mouvement,
            'categorie' => $this->categorie,
            'montant' => (float) $this->montant,
            'reference_document' => $this->reference_document,
            'libelle' => $this->libelle,
            'date_mouvement' => $this->date_mouvement?->toDateString(),
            'annee_catechese' => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
