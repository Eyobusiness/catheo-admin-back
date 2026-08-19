<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'titre' => $this->titre,
            'date_seance' => $this->date_seance?->toDateString(),
            'heure_debut' => $this->heure_debut,
            'heure_fin' => $this->heure_fin,
            'statut' => $this->statut,
            'description' => $this->description,
            'annee_catechese' => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'classe' => new ClasseResource($this->whenLoaded('classe')),
            'module_trimestriel' => new ModuleTrimestrielResource($this->whenLoaded('moduleTrimestriel')),
            'presences' => PresenceResource::collection($this->whenLoaded('presences')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
