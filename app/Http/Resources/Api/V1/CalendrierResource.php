<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalendrierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cibleEntity = $this->cible;

        return [
            'id'                 => $this->uuid,
            'titre'              => $this->titre,
            'type'               => $this->type,
            'date'               => $this->date?->format('Y-m-d'),
            'heure_debut'        => $this->heure_debut,
            'heure_fin'          => $this->heure_fin,
            'lieu'               => $this->lieu,
            'cible_type'         => $this->cible_type ?? 'TOUS',
            'cible_id'           => $cibleEntity?->uuid ?? null,
            'cible_nom'          => $cibleEntity?->nom ?? $cibleEntity?->titre ?? null,
            'description'        => $this->description,
            'statut'             => $this->statut ?? 'Planifié',
            'annee_catechese'    => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'created_at'         => $this->created_at?->toIso8601String(),
        ];
    }
}
