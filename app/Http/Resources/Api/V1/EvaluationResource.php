<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvaluationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $notes = $this->notes;

        $totalNotesCount = $notes ? $notes->count() : $this->notes()->count();
        $avg = $totalNotesCount > 0 ? round($this->notes()->avg('note_obtenue') ?? 0, 2) : 0.0;
        $max = $totalNotesCount > 0 ? (float) ($this->notes()->max('note_obtenue') ?? 0) : 0.0;
        $min = $totalNotesCount > 0 ? (float) ($this->notes()->min('note_obtenue') ?? 0) : 0.0;

        $classe = $this->classe;
        $totalEleves = $classe ? $classe->inscriptionsAnnuelles()->count() : 0;

        $module = $this->moduleTrimestriel;
        $periodeStr = $module ? ($module->nom_trimestre ?: "Trimestre {$module->numero_trimestre}") : null;

        return [
            'id' => $this->uuid,
            'titre' => $this->titre,
            'description' => $this->description,
            'type_eval' => ucfirst($this->type_eval ?? 'interrogation'),
            'type_eval_code' => strtolower($this->type_eval ?? 'interrogation'),
            'periode' => $periodeStr,
            'coefficient' => (float) $this->coefficient,
            'coefficient_label' => 'Coeff. ' . (int) $this->coefficient,
            'note_max' => (float) $this->note_max,
            'bareme_label' => '/' . (int) $this->note_max,
            'date_evaluation' => $this->date_evaluation?->toDateString(),
            'statut' => ucfirst($this->statut ?? 'actif'),
            'statut_code' => $this->statut ?? 'actif',
            'stats' => [
                'moyenne_classe' => $avg,
                'plus_forte_note' => $max,
                'plus_faible_note' => $min,
                'saisies_effectuees' => $totalNotesCount,
                'total_eleves' => $totalEleves,
                'saisies_ratio' => "{$totalNotesCount}/{$totalEleves}",
            ],
            'annee_catechese' => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'module_trimestriel' => new ModuleTrimestrielResource($this->whenLoaded('moduleTrimestriel')),
            'classe' => new ClasseResource($this->whenLoaded('classe')),
            'notes' => NoteResource::collection($this->whenLoaded('notes')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
