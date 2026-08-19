<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SauvegardeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'nom_fichier' => $this->nom_fichier,
            'date' => $this->created_at ? $this->created_at->format('d/m/Y') : null,
            'heure' => $this->created_at ? $this->created_at->format('H:i') : null,
            'taille' => $this->taille_formatted,
            'taille_octets' => $this->taille_octets,
            'cree_par' => $this->cree_par,
            'type' => $this->type,
            'statut' => $this->statut,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
