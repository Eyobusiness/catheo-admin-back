<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaisseParoissialeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->uuid ?? (string) $this->id,
            'uuid'               => $this->uuid,
            'type_mouvement'     => $this->type_mouvement,
            'categorie'          => $this->categorie,
            'montant'            => (float) $this->montant,
            'reference'          => $this->reference_document ?? ('MVT-' . sprintf('%04d', $this->id)),
            'reference_document' => $this->reference_document,
            'libelle'            => $this->libelle,
            'date_mouvement'     => $this->date_mouvement ? (is_string($this->date_mouvement) ? substr($this->date_mouvement, 0, 10) : $this->date_mouvement->toDateString()) : null,
            'annee_catechese_id' => $this->anneeCatechese?->uuid,
            'annee_libelle'      => $this->anneeCatechese?->libelle,
            'annee_catechese'    => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'created_at'         => $this->created_at?->toISOString(),
            'updated_at'         => $this->updated_at?->toISOString(),
        ];
    }
}
