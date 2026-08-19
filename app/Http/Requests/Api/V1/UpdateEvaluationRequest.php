<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'annee_catechese_id' => ['sometimes', 'required', 'string', 'exists:annee_catecheses,uuid'],
            'module_trimestriel_id' => ['sometimes', 'required', 'string', 'exists:modules_trimestriels,uuid'],
            'classe_id' => ['sometimes', 'required', 'string', 'exists:classes,uuid'],
            'titre' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type_eval' => ['sometimes', 'required', 'string', 'in:interrogation,composition,examen,oral,devoir,comportement'],
            'coefficient' => ['sometimes', 'required', 'numeric', 'min:0.1', 'max:10.0'],
            'note_max' => ['sometimes', 'required', 'numeric', 'min:1.0', 'max:100.0'],
            'date_evaluation' => ['sometimes', 'required', 'date'],
            'statut' => ['sometimes', 'required', 'string', 'in:actif,inactif'],
        ];
    }
}
