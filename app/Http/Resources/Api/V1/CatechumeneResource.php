<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatechumeneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                          => $this->uuid,
            'code_catechumene'            => $this->code_catechumene,
            'matricule'                   => $this->code_catechumene,
            'nom'                         => $this->nom,
            'prenoms'                     => $this->prenoms,
            'nom_complet'                 => trim("{$this->prenoms} {$this->nom}"),
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
            'ceb'                         => new CebResource($this->whenLoaded('ceb')),
            'inscriptions_annuelles'      => InscriptionAnnuelleResource::collection($this->whenLoaded('inscriptionsAnnuelles')),
            'parrains_marraines'          => ParrainMarraineResource::collection($this->whenLoaded('parrainsMarraines')),
            'created_at'                  => $this->created_at?->toIso8601String(),
        ];
    }
}
