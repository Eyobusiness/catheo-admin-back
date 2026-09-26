<?php

namespace App\Http\Resources\Api\V1\Organisation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatheoPopulationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cat = $this->catechumene;

        return [
            'inscription_id'    => $this->id,
            'inscription_uuid'  => $this->uuid,
            'code_inscription'  => $this->code_inscription,
            'date_inscription'  => $this->date_inscription?->toDateString(),
            'statut_inscription'=> $this->statut_inscription,
            'catechumene'       => $cat ? [
                'id'              => $cat->id,
                'uuid'            => $cat->uuid,
                'matricule'       => $cat->matricule,
                'nom'             => $cat->nom,
                'prenoms'         => $cat->prenoms,
                'nom_complet'     => trim("{$cat->nom} {$cat->prenoms}"),
                'sexe'            => $cat->sexe,
                'date_naissance'  => $cat->date_naissance?->toDateString(),
                'telephone'       => $cat->telephone,
                'email'           => $cat->email,
                'nom_pere'        => $cat->nom_pere,
                'nom_mere'        => $cat->nom_mere,
                'contact_parent'  => $cat->contact_parent ?? $cat->telephone_parent,
            ] : null,
            'section'           => $this->section ? [
                'id'   => $this->section->id,
                'code' => $this->section->code,
                'nom'  => $this->section->nom,
            ] : null,
            'niveau'            => $this->niveau ? [
                'id'  => $this->niveau->id,
                'nom' => $this->niveau->nom,
            ] : null,
            'classe'            => $this->classe ? [
                'id'  => $this->classe->id,
                'nom' => $this->classe->nom,
            ] : null,
            'annee_catechese'   => $this->anneeCatechese ? [
                'id'      => $this->anneeCatechese->id,
                'libelle' => $this->anneeCatechese->libelle,
                'statut'  => $this->anneeCatechese->statut,
            ] : null,
        ];
    }
}