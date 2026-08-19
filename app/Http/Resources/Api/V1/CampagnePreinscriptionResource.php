<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampagnePreinscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->uuid,
            'titre'                => $this->titre,
            'nom'                  => $this->titre,
            'date_debut'           => $this->date_debut?->toDateString(),
            'date_fin'             => $this->date_fin?->toDateString(),
            'statut'               => $this->statut,
            'est_ouverte'          => (bool) $this->est_ouverte,
            'description'          => $this->description,
            'sections_autorisees'  => $this->sections_autorisees ?? [],
            'public_url'           => $this->public_url,
            'qr_code_url'          => $this->qr_code_url,
            'annee_catechese'      => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'preinscriptions_count'=> $this->whenCounted('preinscriptions'),
            'created_at'           => $this->created_at?->toIso8601String(),
        ];
    }
}
