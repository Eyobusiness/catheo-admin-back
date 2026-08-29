<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DecisionFinAnneeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $inscr = $this->inscriptionAnnuelle;
        $cat = $inscr?->catechumene;
        $classe = $inscr?->classe;
        $niveau = $inscr?->niveau ?? $classe?->niveau;
        $section = $niveau?->section;
        $annee = $inscr?->anneeCatechese;

        $obs = [];
        if (!empty($this->observations) && is_string($this->observations)) {
            $decoded = json_decode($this->observations, true);
            if (is_array($decoded)) {
                $obs = $decoded;
            }
        }

        $decisionFormatted = match(strtolower($this->decision ?? 'admis')) {
            'admis', 'sacrement_valide' => 'Admis',
            'redouble', 'non admis', 'non_admis', 'refuse' => 'Non admis',
            'ajourne', 'ajourné' => 'Ajourné',
            'exclu' => 'Non admis',
            default => ucfirst($this->decision ?? 'Admis'),
        };

        $moyenne = $this->moyenne_annuelle !== null ? (float) $this->moyenne_annuelle : null;

        return [
            'id'                      => $this->uuid,
            'inscription_annuelle_id' => $inscr?->uuid,
            'catechumeneId'           => $cat?->uuid,
            'matricule'               => $cat?->matricule,
            'code_catechumene'        => $cat?->matricule,
            'nomPrenoms'              => $cat ? trim("{$cat->nom} {$cat->prenoms}") : null,
            'nom_prenoms'             => $cat ? trim("{$cat->nom} {$cat->prenoms}") : null,
            'section'                 => $section?->nom,
            'niveau'                  => $niveau?->nom,
            'classe'                  => $classe?->nom,
            'classe_id'               => $classe?->uuid,
            'anneePastorale'          => $annee?->libelle ?? '2025-2026',
            'annee_pastorale'         => $annee?->libelle ?? '2025-2026',
            'moyenneGenerale'         => $moyenne ?? 0.0,
            'moyenne_annuelle'        => $moyenne,
            'presenceCoursPct'        => $obs['presenceCoursPct'] ?? $obs['presence_cours_pct'] ?? 90,
            'presence_cours_pct'      => $obs['presenceCoursPct'] ?? $obs['presence_cours_pct'] ?? 90,
            'presenceMesse'           => $obs['presenceMesse'] ?? $obs['presence_messe'] ?? 'Régulière',
            'presence_messe'          => $obs['presenceMesse'] ?? $obs['presence_messe'] ?? 'Régulière',
            'presenceCEB'             => $obs['presenceCEB'] ?? $obs['presence_ceb'] ?? 'Actif',
            'presence_ceb'            => $obs['presenceCEB'] ?? $obs['presence_ceb'] ?? 'Actif',
            'presenceMouvement'       => $obs['presenceMouvement'] ?? $obs['presence_mouvement'] ?? 'Régulière',
            'presence_mouvement'      => $obs['presenceMouvement'] ?? $obs['presence_mouvement'] ?? 'Régulière',
            'decision'                => $decisionFormatted,
            'decision_code'           => strtolower($this->decision ?? 'admis'),
            'mention'                 => $this->mention,
            'sacrement_recu'          => (bool) $this->sacrement_recu,
            'date_decision'           => $this->date_decision?->toDateString(),
            'observations'            => $this->observations,
            'inscription_annuelle'    => new InscriptionAnnuelleResource($this->whenLoaded('inscriptionAnnuelle')),
            'created_at'              => $this->created_at?->toIso8601String(),
            'updated_at'              => $this->updated_at?->toIso8601String(),
        ];
    }
}

