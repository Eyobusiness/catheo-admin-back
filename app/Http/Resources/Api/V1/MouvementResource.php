<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MouvementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->uuid,
            'nom'                => $this->nom,
            'responsable'        => $this->responsable,
            'telephone'          => $this->telephone,
            'description'        => $this->description,
            'statut'             => ucfirst($this->statut ?? 'Active'),
            'statut_code'        => strtolower($this->statut ?? 'active'),
            'total_inscriptions' => $this->inscriptions_annuelles_count ?? ($this->relationLoaded('inscriptionsAnnuelles') ? $this->inscriptionsAnnuelles->count() : 0),
            'created_at'         => $this->created_at?->toIso8601String(),
        ];
    }
}
