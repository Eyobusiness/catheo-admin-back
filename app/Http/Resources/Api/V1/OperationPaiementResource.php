<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperationPaiementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->uuid,
            'uuid'               => $this->uuid,
            'reference'          => $this->reference,
            'libelle'            => $this->libelle,
            'montant'            => (float) $this->montant,
            'montant_paye'       => (float) $this->montant_paye,
            'remise'             => (float) ($this->remise ?? 0),
            'echeance'           => $this->echeance ? (is_string($this->echeance) ? substr($this->echeance, 0, 10) : $this->echeance->toDateString()) : null,
            'statut'             => $this->statut,
            'annee_catechese_id' => $this->anneeCatechese?->uuid ?? (string) $this->annee_catechese_id,
            'catechumene_id'     => $this->catechumene?->uuid ?? (string) $this->catechumene_id,
            'tarif_id'           => $this->tarif?->uuid ?? (string) $this->tarif_id,
            'catechumene'        => $this->relationLoaded('catechumene') && $this->catechumene ? [
                'id'          => $this->catechumene->uuid,
                'uuid'        => $this->catechumene->uuid,
                'matricule'   => $this->catechumene->matricule,
                'nom'         => $this->catechumene->nom,
                'prenoms'     => $this->catechumene->prenoms,
                'nom_complet' => $this->catechumene->nom_complet,
            ] : null,
            'tarif'              => new TarifResource($this->whenLoaded('tarif')),
            'annee_catechese'    => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}
