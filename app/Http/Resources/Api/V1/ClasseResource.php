<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClasseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->uuid,
            'nom'             => $this->nom,
            'capacite_max'    => (int) ($this->capacite_max ?? 30),
            'statut'          => $this->statut ?? 'active',
            'effectif_actuel' => $this->inscriptions_annuelles_count ?? ($this->relationLoaded('inscriptionsAnnuelles') ? $this->inscriptionsAnnuelles->count() : 0),
            'niveau'          => new NiveauResource($this->whenLoaded('niveau')),
            'annee_catechese' => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}
