<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InscriptionAnnuelleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->uuid,
            'code_inscription'        => $this->code_inscription,
            'date_inscription'        => $this->date_inscription?->toDateString(),
            'statut_inscription'      => $this->statut_inscription,
            'frais_inscription_payes' => (bool) $this->frais_inscription_payes,
            'observation'             => $this->observation,
            'catechumene'             => new CatechumeneResource($this->whenLoaded('catechumene')),
            'annee_catechese'         => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'section'                 => new SectionResource($this->whenLoaded('section')),
            'niveau'                  => new NiveauResource($this->whenLoaded('niveau')),
            'classe'                  => new ClasseResource($this->whenLoaded('classe')),
            'ceb'                     => new CebResource($this->whenLoaded('ceb')),
            'mouvement'               => new MouvementResource($this->whenLoaded('mouvement')),
            'created_at'              => $this->created_at?->toIso8601String(),
        ];
    }
}
