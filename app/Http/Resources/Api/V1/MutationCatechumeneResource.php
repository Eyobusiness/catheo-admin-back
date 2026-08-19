<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MutationCatechumeneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'paroisse_origine_nom' => $this->paroisse_origine_nom,
            'paroisse_destination_nom' => $this->paroisse_destination_nom,
            'motif' => $this->motif,
            'date_mutation' => $this->date_mutation?->toDateString(),
            'statut' => $this->statut,
            'catechumene' => new CatechumeneResource($this->whenLoaded('catechumene')),
            'annee_catechese' => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
