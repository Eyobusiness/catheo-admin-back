<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentGenereResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->uuid ?? (string) $this->id,
            'uuid'               => $this->uuid,
            'reference_document' => $this->reference_document,
            'reference'          => $this->reference_document,
            'titre'              => $this->titre,
            'type_document'      => $this->type_document,
            'contenu'            => $this->contenu,
            'metadonnees'        => $this->metadonnees ?? [],
            'date_generation'    => $this->date_generation ? (is_string($this->date_generation) ? substr($this->date_generation, 0, 10) : $this->date_generation->toDateString()) : null,
            'statut'             => $this->statut,
            'modele_document_id' => $this->modeleDocument?->uuid,
            'modele_titre'       => $this->modeleDocument?->titre,
            'catechumene_id'     => $this->catechumene?->uuid,
            'catechumene'        => $this->whenLoaded('catechumene', function () {
                $prenom = $this->catechumene->prenoms ?? ($this->catechumene->prenom ?? '');
                return [
                    'id'          => $this->catechumene->uuid,
                    'matricule'   => $this->catechumene->matricule,
                    'nom'         => $this->catechumene->nom,
                    'prenom'      => $prenom,
                    'prenoms'     => $prenom,
                    'nom_complet' => trim(($this->catechumene->nom ?? '') . ' ' . $prenom),
                ];
            }),
            'annee_catechese_id' => $this->anneeCatechese?->uuid,
            'annee_libelle'      => $this->anneeCatechese?->libelle,
            'user'               => $this->whenLoaded('user', function () {
                return [
                    'id'    => $this->user->uuid,
                    'name'  => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'created_at'         => $this->created_at?->toISOString(),
            'updated_at'         => $this->updated_at?->toISOString(),
        ];
    }
}
