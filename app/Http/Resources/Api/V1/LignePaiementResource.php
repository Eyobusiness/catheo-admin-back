<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LignePaiementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'designation' => $this->designation,
            'montant' => (float) $this->montant,
            'quantite' => $this->quantite,
            'sous_total' => (float) $this->sous_total,
            'tarif' => new TarifResource($this->whenLoaded('tarif')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
