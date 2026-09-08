<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SacrementExceptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cat = $this->catechumene;
        $activeInsc = $cat?->inscriptionsAnnuelles?->first();

        $nomComplet = trim(($cat?->nom ?? '') . ' ' . ($cat?->prenoms ?? ''));
        $sacrementNom = $this->sacrement?->nom ?? 'Sacrement';
        $dateStr = $this->date_derogation?->toDateString() ?? $this->created_at?->toDateString() ?? now()->toDateString();

        return [
            'id'                     => (string) ($this->uuid ?? $this->id),
            'uuid'                   => (string) ($this->uuid ?? $this->id),
            'catechumeneId'          => (string) ($cat?->uuid ?? $cat?->id ?? $this->catechumene_id),
            'catechumene_id'         => (string) ($cat?->uuid ?? $cat?->id ?? $this->catechumene_id),
            'catechumeneNomComplet'  => $nomComplet,
            'catechumene_nom_complet'=> $nomComplet,
            'matricule'              => $cat?->matricule ?? '',
            'section'                => $activeInsc?->section?->nom ?? $activeInsc?->niveau?->section?->nom ?? '',
            'section_id'             => (string) ($activeInsc?->section?->uuid ?? $activeInsc?->section_id ?? ''),
            'classe'                 => $activeInsc?->classe?->nom ?? '',
            'classe_id'              => (string) ($activeInsc?->classe?->uuid ?? $activeInsc?->classe_id ?? ''),
            'niveau'                 => $activeInsc?->niveau?->nom ?? '',
            'niveau_id'              => (string) ($activeInsc?->niveau?->uuid ?? $activeInsc?->niveau_id ?? ''),
            'annee_catechese_id'     => (string) ($this->anneeCatechese?->uuid ?? $this->annee_catechese_id ?? ''),
            'anneeCatecheseId'       => (string) ($this->anneeCatechese?->uuid ?? $this->annee_catechese_id ?? ''),
            'annee_catechese_libelle'=> $this->anneeCatechese?->libelle ?? '',
            'anneeCatecheseLibelle'  => $this->anneeCatechese?->libelle ?? '',
            'sacrement_id'           => $this->sacrement_id,
            'sacrementType'          => $sacrementNom,
            'sacrement_type'         => $sacrementNom,
            'motif'                  => $this->motif,
            'autorisePar'            => $this->autorise_par ?? '',
            'autorise_par'           => $this->autorise_par ?? '',
            'observation'            => $this->observation ?? '',
            'dateAjout'              => $dateStr,
            'date_derogation'        => $dateStr,
            'statut'                 => $this->statut ?? 'actif',
            'created_at'             => $this->created_at?->toIso8601String(),
            'updated_at'             => $this->updated_at?->toIso8601String(),
        ];
    }
}
