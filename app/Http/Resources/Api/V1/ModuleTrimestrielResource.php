<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModuleTrimestrielResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->uuid,
            'libelle'          => $this->nom,
            'nom'              => $this->nom,
            'numero_trimestre' => $this->numero_trimestre,
            'date_debut'       => $this->date_debut?->toDateString(),
            'date_fin'         => $this->date_fin?->toDateString(),
            'statut'           => $this->statut ?? 'en_cours',
            'annee_catechese'  => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
