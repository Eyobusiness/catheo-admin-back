<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'format' => ['required', 'string', 'in:pdf,excel,csv'],
            'annee_catechese_id' => ['nullable', 'string', 'exists:annee_catecheses,uuid'],
            'section_id' => ['nullable', 'string', 'exists:sections,uuid'],
            'niveau_id' => ['nullable', 'string', 'exists:niveaux,uuid'],
            'classe_id' => ['nullable', 'string', 'exists:classes,uuid'],
            'seance_id' => ['nullable', 'string', 'exists:seances,uuid'],
            'statut' => ['nullable', 'string'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
        ];
    }
}
