<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PreinscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                         => $this->uuid,
            'uuid'                       => $this->uuid,
            'code_dossier'               => $this->code_dossier,
            'type_demande'               => $this->type_demande ?? 'nouvelle_inscription',
            'statut'                     => $this->statut ?? 'en_attente',
            'nom'                        => $this->nom,
            'prenoms'                    => $this->prenoms,
            'nom_complet'                => trim("{$this->prenoms} {$this->nom}"),
            'sexe'                       => $this->sexe,
            'date_naissance'             => $this->date_naissance?->toDateString(),
            'lieu_naissance'             => $this->lieu_naissance,
            'adresse'                    => $this->adresse,
            'telephone'                  => $this->telephone,
            'photo_url'                  => $this->photo_url,
            'photo_profil'               => $this->photo_url,
            'situation_matrimoniale'     => $this->situation_matrimoniale,
            
            // Filiation
            'nom_pere'                   => $this->nom_pere,
            'telephone_pere'             => $this->telephone_pere,
            'nom_mere'                   => $this->nom_mere,
            'telephone_mere'             => $this->telephone_mere,
            'nom_tuteur'                 => $this->nom_tuteur,
            'telephone_tuteur'           => $this->telephone_tuteur,

            // Baptême
            'est_baptise'                => (bool) $this->est_baptise,
            'date_bapteme'               => $this->date_bapteme?->toDateString(),
            'lieu_bapteme'               => $this->lieu_bapteme,
            'paroisse_bapteme'           => $this->paroisse_bapteme,

            // Parrain / Marraine
            'nom_parrain'                => $this->nom_parrain,
            'sexe_parrain'               => $this->sexe_parrain,
            'telephone_parrain'          => $this->telephone_parrain,

            // Documents joints
            'acte_naissance_url'         => $this->acte_naissance_url,

            // Validation
            'notes_validation'           => $this->notes_validation,

            // IDs direct pour le frontend
            'campagne_preinscription_id' => $this->campagne?->uuid ?? (string) $this->campagne_preinscription_id,
            'campagne_id'                => $this->campagne?->uuid ?? (string) $this->campagne_preinscription_id,
            'annee_catechese_id'         => $this->anneeCatechese?->uuid ?? (string) $this->annee_catechese_id,
            'section_souhaite_id'        => $this->sectionSouhaite?->uuid ?? (string) $this->section_souhaite_id,
            'niveau_souhaite_id'         => $this->niveauSouhaite?->uuid ?? (string) $this->niveau_souhaite_id,

            // Relations
            'campagne'                   => new CampagnePreinscriptionResource($this->whenLoaded('campagne')),
            'annee_catechese'            => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'anneeCatechese'             => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'section_souhaite'           => new SectionResource($this->whenLoaded('sectionSouhaite')),
            'sectionSouhaite'            => new SectionResource($this->whenLoaded('sectionSouhaite')),
            'niveau_souhaite'            => new NiveauResource($this->whenLoaded('niveauSouhaite')),
            'niveauSouhaite'             => new NiveauResource($this->whenLoaded('niveauSouhaite')),
            'created_at'                 => $this->created_at?->toIso8601String(),
            'updated_at'                 => $this->updated_at?->toIso8601String(),
        ];
    }

}
