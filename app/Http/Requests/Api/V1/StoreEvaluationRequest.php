<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'annee_catechese_id' => ['required', 'string', 'exists:annee_catecheses,uuid'],
            'module_trimestriel_id' => ['required', 'string', 'exists:modules_trimestriels,uuid'],
            'classe_id' => ['required', 'string', 'exists:classes,uuid'],
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type_eval' => ['required', 'string', 'in:interrogation,composition,examen,oral,devoir,comportement'],
            'coefficient' => ['required', 'numeric', 'min:0.1', 'max:10.0'],
            'note_max' => ['required', 'numeric', 'min:1.0', 'max:100.0'],
            'date_evaluation' => ['required', 'date'],
            'statut' => ['nullable', 'string', 'in:actif,inactif'],
        ];
    }
}
