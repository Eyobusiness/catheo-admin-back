<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnneeCatecheseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'libelle' => $this->libelle,
            'date_debut' => $this->date_debut?->toDateString(),
            'date_fin' => $this->date_fin?->toDateString(),
            'est_active' => $this->est_active,
            'statut' => $this->statut,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
