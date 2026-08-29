<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatechumeneSacrementListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $activeInscription = $this->inscriptionsAnnuelles?->first();

        // Construire l'état rapide des 3 sacrements
        $bapteme = $this->parcoursSacrements->firstWhere('sacrement.code', 'BAPTEME');
        $communion = $this->parcoursSacrements->firstWhere('sacrement.code', 'PREMIERE_COMMUNION');
        $confirmation = $this->parcoursSacrements->firstWhere('sacrement.code', 'CONFIRMATION');

        $statutBapteme = $bapteme ? $bapteme->statut : ($this->est_baptise ? 'valide' : 'non_recu');
        $statutCommunion = $communion ? $communion->statut : ($this->date_premiere_communion ? 'valide' : 'non_recu');
        $statutConfirmation = $confirmation ? $confirmation->statut : ($this->date_confirmation ? 'valide' : 'non_recu');

        return [
            'id'                          => $this->uuid,
            'uuid'                        => $this->uuid,
            'matricule'                   => $this->matricule,
            'code_catechumene'            => $this->matricule,
            'nom'                         => $this->nom,
            'prenom'                      => $this->prenoms,
            'prenoms'                     => $this->prenoms,
            'nom_complet'                 => trim("{$this->nom} {$this->prenoms}"),
            'sexe'                        => $this->sexe,
            'date_naissance'              => $this->date_naissance?->toDateString(),
            'telephone'                   => $this->telephone,
            'statut'                      => $this->statut,

            // Section, Niveau, Classe dynamiques (depuis la base)
            'section_id'                  => $activeInscription?->section?->uuid ?? $activeInscription?->niveau?->section?->uuid,
            'section_nom'                 => $activeInscription?->section?->nom ?? $activeInscription?->niveau?->section?->nom,
            'niveau_id'                   => $activeInscription?->niveau?->uuid,
            'niveau_nom'                  => $activeInscription?->niveau?->nom,
            'classe_id'                   => $activeInscription?->classe?->uuid,
            'classe_nom'                  => $activeInscription?->classe?->nom,
            'annee_pastorale'             => $activeInscription?->anneeCatechese?->libelle,

            // Statuts Sacramentels
            'sacrements_status'           => [
                'bapteme'            => $statutBapteme,
                'premiere_communion' => $statutCommunion,
                'confirmation'       => $statutConfirmation,
            ],

            // Détail rapide
            'est_baptise'                 => (bool) ($statutBapteme === 'valide'),
            'date_bapteme'                => $bapteme?->date_sacrement?->toDateString() ?? $this->date_bapteme?->toDateString(),
            'date_premiere_communion'     => $communion?->date_sacrement?->toDateString() ?? $this->date_premiere_communion?->toDateString(),
            'date_confirmation'           => $confirmation?->date_sacrement?->toDateString() ?? $this->date_confirmation?->toDateString(),

            'created_at'                  => $this->created_at?->toIso8601String(),
        ];
    }
}
