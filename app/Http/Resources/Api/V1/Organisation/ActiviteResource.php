<?php

namespace App\Http\Resources\Api\V1\Organisation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActiviteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->uuid,
            'id_interne'      => $this->id,
            'organisation_id' => $this->organisation?->uuid ?? $this->organisation_id,
            'code'            => $this->code,
            'titre'           => $this->titre,
            'description'     => $this->description,
            'type_activite'   => $this->type_activite,
            'date_debut'      => $this->date_debut?->toIso8601String(),
            'date_fin'        => $this->date_fin?->toIso8601String(),
            'lieu'            => $this->lieu,
            'responsable_id'  => $this->responsable?->uuid ?? $this->responsable_id,
            'responsable'     => new MembreResource($this->whenLoaded('responsable')),
            'statut'          => $this->statut,
            'taux_execution'  => (float) $this->taux_execution,
            'observation'     => $this->observation,
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
