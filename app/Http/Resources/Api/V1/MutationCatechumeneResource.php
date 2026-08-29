<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MutationCatechumeneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->uuid,
            'catechumene_id'           => $this->catechumene?->uuid,
            'annee_catechese_id'       => $this->anneeCatechese?->uuid,
            'matricule'                => $this->catechumene?->matricule,
            'nom_complet'              => $this->catechumene ? trim("{$this->catechumene->nom} {$this->catechumene->prenoms}") : null,
            'paroisse_origine_nom'     => $this->paroisse_origine_nom,
            'paroisse_destination_nom' => $this->paroisse_destination_nom,
            'motif'                    => $this->motif,
            'date_mutation'            => $this->date_mutation?->toDateString(),
            'statut'                   => $this->statut,
            'catechumene'              => new CatechumeneResource($this->whenLoaded('catechumene')),
            'annee_catechese'          => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'created_at'               => $this->created_at?->toIso8601String(),
            'updated_at'               => $this->updated_at?->toIso8601String(),
        ];
    }
}
