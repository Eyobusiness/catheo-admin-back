<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DonCotisationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'donateur_nom' => $this->donateur_nom,
            'type_don' => $this->type_don,
            'montant' => (float) $this->montant,
            'description' => $this->description,
            'date_reception' => $this->date_reception?->toDateString(),
            'numero_recu' => $this->numero_recu,
            'annee_catechese' => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'mouvement' => new MouvementResource($this->whenLoaded('mouvement')),
            'ceb' => new CebResource($this->whenLoaded('ceb')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
