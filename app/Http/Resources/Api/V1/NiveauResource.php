<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NiveauResource extends JsonResource
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
            'duree_annees' => $this->duree_annees,
            'ordre_affichage' => $this->ordre_affichage,
            'section' => new SectionResource($this->whenLoaded('section')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
