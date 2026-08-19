<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActiviteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'titre' => $this->titre,
            'description' => $this->description,
            'lieu' => $this->lieu,
            'date_debut' => $this->date_debut?->toDateString(),
            'date_fin' => $this->date_fin?->toDateString(),
            'heure_debut' => $this->heure_debut,
            'heure_fin' => $this->heure_fin,
            'statut' => $this->statut,
            'type_activite' => new TypeActiviteResource($this->whenLoaded('typeActivite')),
            'annee_catechese' => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'sections' => SectionResource::collection($this->whenLoaded('sections')),
            'niveaux' => NiveauResource::collection($this->whenLoaded('niveaux')),
            'classes' => ClasseResource::collection($this->whenLoaded('classes')),
            'animateurs' => AnimateurResource::collection($this->whenLoaded('animateurs')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
