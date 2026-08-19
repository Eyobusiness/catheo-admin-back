<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PreinscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->uuid,
            'code_dossier'           => $this->code_dossier,
            'type_demande'           => $this->type_demande,
            'nom'                    => $this->nom,
            'prenoms'                => $this->prenoms,
            'nom_complet'            => trim("{$this->prenoms} {$this->nom}"),
            'sexe'                   => $this->sexe,
            'date_naissance'         => $this->date_naissance?->toDateString(),
            'lieu_naissance'         => $this->lieu_naissance,
            'adresse'                => $this->adresse,
            'telephone'              => $this->telephone,
            'photo_url'              => $this->photo_url,
            'situation_matrimoniale' => $this->situation_matrimoniale,
            
            // Filiation
            'nom_pere'               => $this->nom_pere,
            'telephone_pere'         => $this->telephone_pere,
            'nom_mere'               => $this->nom_mere,
            'telephone_mere'         => $this->telephone_mere,
            'nom_tuteur'             => $this->nom_tuteur,
            'telephone_tuteur'       => $this->telephone_tuteur,

            // Baptême
            'est_baptise'            => (bool) $this->est_baptise,
            'date_bapteme'           => $this->date_bapteme?->toDateString(),
            'lieu_bapteme'           => $this->lieu_bapteme,
            'paroisse_bapteme'       => $this->paroisse_bapteme,

            // Parrain / Marraine
            'nom_parrain'            => $this->nom_parrain,
            'sexe_parrain'           => $this->sexe_parrain,
            'telephone_parrain'      => $this->telephone_parrain,

            // Documents joints
            'acte_naissance_url'     => $this->acte_naissance_url,

            // Statut et validation
            'statut'                 => $this->statut,
            'notes_validation'       => $this->notes_validation,

            // Relations
            'campagne'               => new CampagnePreinscriptionResource($this->whenLoaded('campagne')),
            'annee_catechese'        => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'section_souhaite'       => new SectionResource($this->whenLoaded('sectionSouhaite')),
            'niveau_souhaite'        => new NiveauResource($this->whenLoaded('niveauSouhaite')),
            'created_at'             => $this->created_at?->toIso8601String(),
        ];
    }
}
