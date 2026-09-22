<?php

namespace App\Http\Resources\Api\V1\Organisation\Pelerinage;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampagnePelerinageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'uuid'                   => $this->uuid,
            'organisation_id'        => $this->organisation_id,
            'activite_id'            => $this->activite_id,
            'code'                   => $this->code,
            'nom'                    => $this->nom,
            'description'            => $this->description,
            'lieu_depart'            => $this->lieu_depart,
            'destination'            => $this->destination,
            'date_depart'            => $this->date_depart?->format('Y-m-d'),
            'heure_depart'           => $this->heure_depart,
            'date_fin'               => $this->date_fin?->format('Y-m-d'),
            'heure_fin'              => $this->heure_fin,
            'date_debut_inscription' => $this->date_debut_inscription?->format('Y-m-d'),
            'date_fin_inscription'   => $this->date_fin_inscription?->format('Y-m-d'),
            'capacite'               => $this->capacite,
            'places_occupees'        => $this->placesOccupees(),
            'est_complete'           => $this->estComplete(),
            'statut'                 => $this->statut,
            'observation'            => $this->observation,
            'activite'               => $this->whenLoaded('activite', function () {
                return [
                    'id'    => $this->activite->id,
                    'code'  => $this->activite->code,
                    'titre' => $this->activite->titre,
                ];
            }),
            'tarifs'                 => TarifPelerinageResource::collection($this->whenLoaded('tarifs')),
            'total_inscrits'         => $this->when(isset($this->inscriptions_count), $this->inscriptions_count),
            'created_at'             => $this->created_at?->toIso8601String(),
            'updated_at'             => $this->updated_at?->toIso8601String(),
        ];
    }
}
