<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InscriptionAnnuelleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cat = $this->catechumene;

        return [
            'id'                      => $this->uuid,
            'code_inscription'        => $this->code_inscription,
            'date_inscription'        => $this->date_inscription?->toDateString(),
            'statut_inscription'      => $this->statut_inscription,
            'statut'                  => $this->statut_inscription,
            'frais_inscription_payes' => (bool) $this->frais_inscription_payes,
            'frais_payes'             => (bool) $this->frais_inscription_payes,
            'observation'             => $this->observation,

            // IDs directes pour les formulaires frontend
            'catechumene_id'          => $cat?->uuid,
            'catechumeneId'           => $cat?->uuid,
            'annee_catechese_id'      => $this->anneeCatechese?->uuid,
            'anneeCatecheseId'        => $this->anneeCatechese?->uuid,
            'section_id'              => $this->section?->uuid ?? $this->niveau?->section?->uuid,
            'sectionId'               => $this->section?->uuid ?? $this->niveau?->section?->uuid,
            'niveau_id'               => $this->niveau?->uuid,
            'niveauId'                => $this->niveau?->uuid,
            'classe_id'               => $this->classe?->uuid,
            'classeId'                => $this->classe?->uuid,
            'ceb_id'                  => $this->ceb?->uuid,
            'mouvement_id'            => $this->mouvement?->uuid,

            // Accès directs catéchumène pour les tableaux Angular
            'matricule'               => $cat?->matricule,
            'code_catechumene'        => $cat?->matricule,
            'nom'                     => $cat?->nom,
            'prenom'                  => $cat?->prenoms,
            'prenoms'                 => $cat?->prenoms,
            'nom_complet'             => $cat ? trim("{$cat->nom} {$cat->prenoms}") : null,
            'nomPrenoms'              => $cat ? trim("{$cat->nom} {$cat->prenoms}") : null,
            'sexe'                    => $cat?->sexe,
            'date_naissance'          => $cat?->date_naissance?->toDateString(),
            'photo_url'               => $cat?->photo_path,
            'photo_path'              => $cat?->photo_path,
            'telephone'               => $cat?->telephone,
            'telephone_parent'        => $cat?->telephone_tuteur ?? $cat?->telephone_pere ?? $cat?->telephone_mere ?? $cat?->telephone,

            // Informations sacramentelles
            'est_baptise'             => (bool) $cat?->est_baptise,
            'date_bapteme'            => $cat?->date_bapteme?->toDateString(),
            'paroisse_bapteme'        => $cat?->paroisse_bapteme,
            'num_carnet_bapteme'      => $cat?->num_carnet_bapteme,
            'date_premiere_communion' => $cat?->date_premiere_communion?->toDateString(),
            'paroisse_premiere_communion' => $cat?->paroisse_premiere_communion,
            'date_confirmation'       => $cat?->date_confirmation?->toDateString(),
            'paroisse_confirmation'   => $cat?->paroisse_confirmation,

            // Relations
            'catechumene'             => $this->whenLoaded('catechumene', function () use ($cat) {
                return [
                    'id'          => $cat?->uuid,
                    'matricule'   => $cat?->matricule,
                    'nom'         => $cat?->nom,
                    'prenom'      => $cat?->prenoms,
                    'prenoms'     => $cat?->prenoms,
                    'nom_complet' => $cat ? trim("{$cat->nom} {$cat->prenoms}") : null,
                    'sexe'        => $cat?->sexe,
                    'telephone'   => $cat?->telephone,
                    'est_baptise' => (bool) $cat?->est_baptise,
                    'date_bapteme'=> $cat?->date_bapteme?->toDateString(),
                    'paroisse_bapteme' => $cat?->paroisse_bapteme,
                    'num_carnet_bapteme' => $cat?->num_carnet_bapteme,
                    'date_premiere_communion' => $cat?->date_premiere_communion?->toDateString(),
                    'paroisse_premiere_communion' => $cat?->paroisse_premiere_communion,
                    'date_confirmation' => $cat?->date_confirmation?->toDateString(),
                    'paroisse_confirmation' => $cat?->paroisse_confirmation,
                ];
            }),
            'annee_catechese'         => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'section'                 => new SectionResource($this->section ?? $this->niveau?->section),
            'niveau'                  => new NiveauResource($this->whenLoaded('niveau')),
            'classe'                  => new ClasseResource($this->whenLoaded('classe')),
            'ceb'                     => new CebResource($this->whenLoaded('ceb')),
            'mouvement'               => new MouvementResource($this->whenLoaded('mouvement')),
            'created_at'              => $this->created_at?->toIso8601String(),
            'updated_at'              => $this->updated_at?->toIso8601String(),
        ];
    }
}
