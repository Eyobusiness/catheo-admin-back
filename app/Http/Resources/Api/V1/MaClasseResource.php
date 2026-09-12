<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaClasseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $affectation = $this->resource['affectation'];
        $classe = $this->resource['classe'];
        $animateur = $this->resource['animateur'];
        $annee = $this->resource['annee_catechese'];
        $niveau = $classe?->niveau;
        $section = $niveau?->section;
        $eleves = collect($this->resource['eleves'] ?? []);

        $mappedEleves = $eleves->map(function ($inscription) {
            $c = $inscription->catechumene;
            $catId = $c?->uuid ?? $inscription->uuid ?? $inscription->id;
            $nom = $c?->nom ?? '';
            $prenoms = $c?->prenoms ?? '';
            $nomComplet = $c?->nom_complet ?? trim("{$nom} {$prenoms}");
            if (empty($nomComplet)) {
                $nomComplet = "Élève #{$inscription->id}";
            }

            $telephoneParent = $c?->telephone_parent 
                ?? $c?->telephone_pere 
                ?? $c?->telephone_mere 
                ?? $c?->telephone_tuteur;

            $nomParent = null;
            if (!empty($c?->telephone_pere)) {
                $nomParent = $c?->nom_pere ? "Père ({$c->nom_pere})" : "Père";
            } elseif (!empty($c?->telephone_mere)) {
                $nomParent = $c?->nom_mere ? "Mère ({$c->nom_mere})" : "Mère";
            } elseif (!empty($c?->telephone_tuteur)) {
                $nomParent = $c?->nom_tuteur ? "Tuteur ({$c->nom_tuteur})" : "Tuteur";
            }

            return [
                'id'               => $catId,
                'uuid'             => $c?->uuid ?? $inscription->uuid,
                'inscription_id'   => $inscription->uuid ?? $inscription->id,
                'catechumene_id'   => $c?->uuid ?? $c?->id,
                'matricule'        => $c?->matricule ?? '',
                'nom'              => $nom,
                'prenoms'          => $prenoms,
                'nom_complet'      => $nomComplet,
                'sexe'             => $c?->sexe ?? '',
                'date_naissance'   => $c?->date_naissance ? (is_string($c->date_naissance) ? $c->date_naissance : $c->date_naissance->format('Y-m-d')) : null,
                'telephone'        => $c?->telephone,
                'telephone_parent' => $telephoneParent,
                'telephone_pere'   => $c?->telephone_pere,
                'nom_pere'         => $c?->nom_pere,
                'telephone_mere'   => $c?->telephone_mere,
                'nom_mere'         => $c?->nom_mere,
                'telephone_tuteur' => $c?->telephone_tuteur,
                'nom_tuteur'       => $c?->nom_tuteur,
                'nom_parent'       => $nomParent,
                'statut'           => $inscription->statut ?? 'inscrit',
                'photo'            => $c?->photo ?? null,
            ];
        })->values();

        return [
            'animateur' => [
                'id'          => $animateur->uuid ?? $animateur->id,
                'uuid'        => $animateur->uuid,
                'numero'      => $animateur->numero ?? $animateur->telephone,
                'nom'         => $animateur->nom,
                'prenoms'     => $animateur->prenoms,
                'nom_complet' => $animateur->nom_complet ?? trim(($animateur->prenoms ?? '') . ' ' . ($animateur->nom ?? '')),
                'telephone'   => $animateur->telephone,
                'email'       => $animateur->email,
            ],
            'annee_catechese' => [
                'id'      => $annee->uuid ?? $annee->id,
                'uuid'    => $annee->uuid,
                'libelle' => $annee->libelle,
                'statut'  => $annee->statut,
            ],
            'affectation' => [
                'id'             => $affectation->uuid ?? $affectation->id,
                'uuid'           => $affectation->uuid,
                'role_animateur' => $affectation->role_animateur ?? 'titulaire',
            ],
            'classe' => [
                'id'              => $classe->uuid ?? $classe->id,
                'uuid'            => $classe->uuid,
                'nom'             => $classe->nom,
                'capacite_max'    => (int) ($classe->capacite_max ?? 30),
                'statut'          => $classe->statut ?? 'active',
                'effectif_actuel' => $mappedEleves->count(),
            ],
            'niveau' => $niveau ? [
                'id'          => $niveau->uuid ?? $niveau->id,
                'uuid'        => $niveau->uuid,
                'nom'         => $niveau->nom,
                'description' => $niveau->description,
            ] : null,
            'section' => $section ? [
                'id'   => $section->uuid ?? $section->id,
                'uuid' => $section->uuid,
                'code' => $section->code,
                'nom'  => $section->nom,
            ] : null,
            'total_eleves' => $mappedEleves->count(),
            'eleves'       => $mappedEleves,
            'catechumenes' => $mappedEleves,
        ];
    }
}
