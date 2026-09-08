<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        if (isset($data['nom']) && !isset($data['titre'])) {
            $data['titre'] = $data['nom'];
        }
        if (isset($data['observation']) && !isset($data['description'])) {
            $data['description'] = $data['observation'];
        }
        if (isset($data['type']) && !isset($data['type_eval'])) {
            $data['type_eval'] = strtolower($data['type']);
        }
        if (isset($data['bareme']) && !isset($data['note_max'])) {
            $data['note_max'] = $data['bareme'];
        }
        if (isset($data['date']) && !isset($data['date_evaluation'])) {
            $data['date_evaluation'] = $data['date'];
        }
        if (isset($data['anneePastorale']) && !isset($data['annee_catechese_id'])) {
            $data['annee_catechese_id'] = $data['anneePastorale'];
        }
        if (isset($data['classe']) && !isset($data['classe_id']) && is_string($data['classe'])) {
            $data['classe_id'] = $data['classe'];
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'titre'                 => ['required', 'string', 'max:255'],
            'description'           => ['nullable', 'string'],
            'type_eval'             => ['nullable', 'string'],
            'coefficient'           => ['nullable', 'numeric', 'min:0.1', 'max:20'],
            'note_max'              => ['nullable', 'numeric', 'min:1', 'max:100'],
            'date_evaluation'       => ['required', 'date'],
            'statut'                => ['nullable', 'string'],
            'annee_catechese_id'    => ['nullable', 'string'],
            'classe_id'             => ['nullable', 'string'],
            'module_trimestriel_id' => ['nullable', 'string'],
            'periode'               => ['nullable', 'string'],
            'section_id'            => ['nullable', 'string'],
            'session_id'            => ['nullable', 'string'],
            'niveau_id'             => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'titre.required'           => 'Le nom/titre de l\'évaluation est obligatoire.',
            'date_evaluation.required' => 'La date de l\'évaluation est obligatoire.',
            'coefficient.min'          => 'Le coefficient doit être d\'au moins 0.1.',
            'coefficient.max'          => 'Le coefficient ne peut dépasser 20.',
            'note_max.min'             => 'La note maximale (barème) doit être d\'au moins 1.',
            'note_max.max'             => 'La note maximale ne peut dépasser 100.',
        ];
    }
}
