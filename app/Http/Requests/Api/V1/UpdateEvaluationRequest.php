<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEvaluationRequest extends FormRequest
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
            'titre'                 => ['sometimes', 'required', 'string', 'max:255'],
            'description'           => ['nullable', 'string'],
            'type_eval'             => ['nullable', 'string'],
            'coefficient'           => ['nullable', 'numeric', 'min:0.1', 'max:20'],
            'note_max'              => ['nullable', 'numeric', 'min:1', 'max:100'],
            'date_evaluation'       => ['sometimes', 'required', 'date'],
            'statut'                => ['nullable', 'string'],
            'annee_catechese_id'    => ['nullable', 'string'],
            'classe_id'             => ['nullable', 'string'],
            'module_trimestriel_id' => ['nullable', 'string'],
            'periode'               => ['nullable', 'string'],
        ];
    }
}
