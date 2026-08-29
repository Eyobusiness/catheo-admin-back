<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatechumeneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $latestInscription = $this->inscriptionsAnnuelles?->first();

        return [
            'id'                          => $this->uuid,
            'uuid'                        => $this->uuid,
            'matricule'                   => $this->matricule ?? $this->code_catechumene,
            'code_catechumene'            => $this->matricule ?? $this->code_catechumene,
            'nom'                         => $this->nom,
            'prenom'                      => $this->prenoms,
            'prenoms'                     => $this->prenoms,
            'nom_complet'                 => trim("{$this->nom} {$this->prenoms}"),
            'nom_prenoms'                 => trim("{$this->nom} {$this->prenoms}"),
            'nomPrenoms'                  => trim("{$this->nom} {$this->prenoms}"),
            'sexe'                        => $this->sexe,
            'date_naissance'              => $this->date_naissance?->toDateString(),
            'lieu_naissance'              => $this->lieu_naissance,
            'adresse'                     => $this->adresse,
            'domicile'                    => $this->domicile,
            'profession'                  => $this->profession,
            'classe_scolaire'             => $this->classe_scolaire,
            'situation_matrimoniale'      => $this->situation_matrimoniale,
            'telephone'                   => $this->telephone,
            'photo_path'                  => $this->photo_path,
            'photo_url'                   => $this->photo_path,

            // Shortcuts Inscription Active pour les sélecteurs et tables
            'classe_id'                   => $latestInscription?->classe?->uuid,
            'classe_nom'                  => $latestInscription?->classe?->nom,
            'niveau_id'                   => $latestInscription?->niveau?->uuid,
            'niveau_nom'                  => $latestInscription?->niveau?->nom,
            'section_id'                  => $latestInscription?->section?->uuid ?? $latestInscription?->niveau?->section?->uuid,
            'section_nom'                 => $latestInscription?->section?->nom ?? $latestInscription?->niveau?->section?->nom,
            'annee_catechese_id'          => $latestInscription?->anneeCatechese?->uuid,
            'annee_libelle'               => $latestInscription?->anneeCatechese?->libelle,
            
            // Filiation & Tuteurs
            'nom_pere'                    => $this->nom_pere,
            'origine_pere'                => $this->origine_pere,
            'telephone_pere'              => $this->telephone_pere,
            'nom_mere'                    => $this->nom_mere,
            'origine_mere'                => $this->origine_mere,
            'telephone_mere'              => $this->telephone_mere,
            'nom_tuteur'                  => $this->nom_tuteur,
            'telephone_tuteur'            => $this->telephone_tuteur,

            // Sacrements
            'est_baptise'                 => (bool) $this->est_baptise,
            'num_carnet_bapteme'          => $this->num_carnet_bapteme,
            'date_bapteme'                => $this->date_bapteme?->toDateString(),
            'lieu_bapteme'                => $this->lieu_bapteme,
            'diocese_bapteme'             => $this->diocese_bapteme,
            'ville_bapteme'               => $this->ville_bapteme,
            'paroisse_bapteme'            => $this->paroisse_bapteme,
            'date_premiere_communion'     => $this->date_premiere_communion?->toDateString(),
            'paroisse_premiere_communion' => $this->paroisse_premiere_communion,
            'date_confirmation'           => $this->date_confirmation?->toDateString(),
            'paroisse_confirmation'       => $this->paroisse_confirmation,
            'ministre_confirmation'       => $this->ministre_confirmation,

            // Statut & Relations
            'statut'                      => $this->statut,
            'ceb'                         => $this->relationLoaded('ceb') && $this->ceb ? new CebResource($this->ceb) : null,
            'inscriptions_annuelles'      => $this->whenLoaded('inscriptionsAnnuelles', function () {
                return $this->inscriptionsAnnuelles->map(function ($ins) {
                    return [
                        'id'                      => $ins->uuid,
                        'code_inscription'        => $ins->code_inscription,
                        'date_inscription'        => $ins->date_inscription?->toDateString(),
                        'statut_inscription'      => $ins->statut_inscription,
                        'frais_inscription_payes' => (bool) $ins->frais_inscription_payes,
                        'annee_catechese_id'      => $ins->anneeCatechese?->uuid,
                        'annee_libelle'           => $ins->anneeCatechese?->libelle,
                        'section_id'              => $ins->section?->uuid ?? $ins->niveau?->section?->uuid,
                        'section_nom'             => $ins->section?->nom ?? $ins->niveau?->section?->nom,
                        'niveau_id'               => $ins->niveau?->uuid,
                        'niveau_nom'              => $ins->niveau?->nom,
                        'classe_id'               => $ins->classe?->uuid,
                        'classe_nom'              => $ins->classe?->nom,
                    ];
                });
            }),
            'parrains_marraines'          => $this->relationLoaded('parrainsMarraines') ? ParrainMarraineResource::collection($this->parrainsMarraines) : null,
            'created_at'                  => $this->created_at?->toIso8601String(),
        ];
    }
}
