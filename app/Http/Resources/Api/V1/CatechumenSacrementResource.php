<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatechumenSacrementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->uuid,
            'uuid'             => $this->uuid,
            'statut'           => $this->statut, // preparation, valide
            'date_sacrement'   => $this->date_sacrement?->toDateString(),
            'lieu'             => $this->lieu,
            'paroisse_nom'     => $this->paroisse_nom ?? $this->lieu,
            'celebrant'        => $this->celebrant,
            'numero_registre'  => $this->numero_registre,
            'num_carnet'       => $this->num_carnet,
            'observations'     => $this->observations,
            'validated_at'     => $this->validated_at?->toIso8601String(),
            'sacrement'        => $this->whenLoaded('sacrement', fn() => new SacrementResource($this->sacrement)),
            'annee_pastorale'  => $this->whenLoaded('anneeCatechese', fn() => $this->anneeCatechese?->libelle),
            'validated_by'     => $this->whenLoaded('validator', fn() => $this->validator ? [
                'id'   => $this->validator->uuid,
                'name' => $this->validator->name,
            ] : null),
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
