<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'nom' => $this->nom,
            'code' => $this->code,
            'description' => $this->description,
            'statut' => ucfirst($this->statut ?? 'actif'), // Actif, Inactif
            'statut_code' => $this->statut ?? 'actif',
            'ordre_affichage' => $this->ordre_affichage,
            'niveaux_count' => $this->niveaux ? $this->niveaux->count() : 0,
            'niveaux' => NiveauResource::collection($this->whenLoaded('niveaux')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
