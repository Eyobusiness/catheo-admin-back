<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TarifResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->uuid,
            'uuid'                => $this->uuid,
            'intitule'            => $this->intitule,
            'nom'                 => $this->intitule,
            'description'         => $this->description,
            'montant'             => (float) $this->montant,
            'est_obligatoire'     => (bool) $this->est_obligatoire,
            'type_tarif'          => $this->type_tarif,
            'statut'              => $this->statut ?? 'actif',
            'periode_debut'       => $this->periode_debut?->toDateString(),
            'periode_fin'         => $this->periode_fin?->toDateString(),
            
            // IDs directes pour les formulaires Angular
            'annee_catechese_id'  => $this->anneeCatechese?->uuid,
            'anneeCatecheseId'    => $this->anneeCatechese?->uuid,
            'annee_libelle'       => $this->anneeCatechese?->libelle,
            'niveau_id'           => $this->niveau?->uuid,
            'niveauId'            => $this->niveau?->uuid,
            'niveau_nom'          => $this->niveau?->nom,
            'niveau_ids'          => $this->relationLoaded('niveaux') ? $this->niveaux->pluck('uuid')->toArray() : [],
            'niveauxIds'          => $this->relationLoaded('niveaux') ? $this->niveaux->pluck('uuid')->toArray() : [],

            // Relations
            'annee_catechese'     => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'niveau'              => new NiveauResource($this->whenLoaded('niveau')),
            'niveaux'             => NiveauResource::collection($this->whenLoaded('niveaux')),
            'created_at'          => $this->created_at?->toIso8601String(),
            'updated_at'          => $this->updated_at?->toIso8601String(),
        ];
    }
}