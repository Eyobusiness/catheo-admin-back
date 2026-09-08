<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvaluationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $notes = $this->relationLoaded('notes') ? $this->notes : null;

        $totalNotesCount = $notes ? $notes->count() : $this->notes()->count();
        $avg = $totalNotesCount > 0 ? round((float) ($this->notes()->avg('note_obtenue') ?? 0), 2) : 0.0;
        $max = $totalNotesCount > 0 ? round((float) ($this->notes()->max('note_obtenue') ?? 0), 2) : 0.0;
        $min = $totalNotesCount > 0 ? round((float) ($this->notes()->min('note_obtenue') ?? 0), 2) : 0.0;

        $classe = $this->classe;
        $totalEleves = $classe ? $classe->inscriptionsAnnuelles()->count() : 0;

        $module = $this->moduleTrimestriel;
        $periodeStr = $module ? ($module->nom_trimestre ?: "Trimestre {$module->numero_trimestre}") : 'Trimestre 1';

        $typeMap = [
            'interrogation' => 'Interrogation',
            'devoir'        => 'Devoir',
            'composition'   => 'Composition',
            'examen'        => 'Examen',
            'oral'          => 'Oral',
            'comportement'  => 'Devoir',
        ];
        $typeFormatted = $typeMap[strtolower($this->type_eval ?? 'interrogation')] ?? ucfirst($this->type_eval ?? 'Interrogation');

        $statutFormatted = strtolower($this->statut ?? 'actif') === 'inactif' ? 'Inactif' : 'Actif';
        $statutCode = strtolower($this->statut ?? 'actif') === 'inactif' ? 'inactif' : 'actif';

        $dateStr = $this->date_evaluation ? (is_string($this->date_evaluation) ? substr($this->date_evaluation, 0, 10) : $this->date_evaluation->format('Y-m-d')) : null;

        return [
            'id'                 => $this->uuid,
            'nom'                => $this->titre,
            'titre'              => $this->titre,
            'description'        => $this->description,
            'observation'        => $this->description,
            'type'               => $typeFormatted,
            'type_eval'          => $typeFormatted,
            'type_eval_code'     => strtolower($this->type_eval ?? 'interrogation'),
            'periode'            => $periodeStr,
            'coefficient'        => (float) $this->coefficient,
            'coefficient_label'  => 'Coeff. ' . (int) $this->coefficient,
            'bareme'             => (float) $this->note_max,
            'note_max'           => (float) $this->note_max,
            'bareme_label'       => '/' . (int) $this->note_max,
            'date'               => $dateStr,
            'date_evaluation'    => $dateStr,
            'statut'             => $statutFormatted,
            'statut_code'        => $statutCode,
            'anneePastorale'     => $this->anneeCatechese?->libelle ?? '2025-2026',
            'annee_catechese_id' => $this->anneeCatechese?->uuid,
            'classe_id'          => $this->classe?->uuid,
            'section'            => $this->classe?->niveau?->section?->nom,
            'session'            => $this->classe?->niveau?->section?->nom,
            'section_id'         => $this->classe?->niveau?->section?->uuid,
            'niveau'             => $this->classe?->niveau?->nom,
            'niveau_id'          => $this->classe?->niveau?->uuid,
            'classe'             => $this->relationLoaded('classe') ? new ClasseResource($this->classe) : $this->classe?->nom,
            'stats'              => [
                'moyenne_classe'     => $avg,
                'plus_forte_note'    => $max,
                'plus_faible_note'   => $min,
                'saisies_effectuees' => $totalNotesCount,
                'total_eleves'       => $totalEleves,
                'saisies_ratio'      => "{$totalNotesCount}/{$totalEleves}",
            ],
            'annee_catechese'    => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'module_trimestriel' => new ModuleTrimestrielResource($this->whenLoaded('moduleTrimestriel')),
            'notes'              => NoteResource::collection($this->whenLoaded('notes')),
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}

