<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnonceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'titre' => $this->titre,
            'contenu' => $this->contenu,
            'cible' => $this->cible,
            'date_publication' => $this->date_publication?->toDateString(),
            'date_expiration' => $this->date_expiration?->toDateString(),
            'statut' => $this->statut,
            'annee_catechese' => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'section' => new SectionResource($this->whenLoaded('section')),
            'niveau' => new NiveauResource($this->whenLoaded('niveau')),
            'classe' => new ClasseResource($this->whenLoaded('classe')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
