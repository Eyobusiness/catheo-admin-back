<?php

namespace App\Http\Resources\Api\V1\Organisation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->uuid,
            'id_interne'      => $this->id,
            'organisation_id' => $this->organisation?->uuid ?? $this->organisation_id,
            'nom'             => $this->nom,
            'prenoms'         => $this->prenoms,
            'nom_complet'     => $this->nom_complet,
            'sexe'            => $this->sexe,
            'date_naissance'  => $this->date_naissance?->toDateString(),
            'telephone'       => $this->telephone,
            'email'           => $this->email,
            'quartier'        => $this->quartier,
            'adresse'         => $this->adresse,
            'fonction'        => $this->fonction,
            'date_entree'     => $this->date_entree?->toDateString(),
            'statut'          => $this->statut,
            'photo_path'      => $this->photo_path,
            'observation'     => $this->observation,
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
