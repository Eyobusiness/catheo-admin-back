<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreActiviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'annee_catechese_id' => ['required', 'string', 'exists:annee_catecheses,uuid'],
            'type_activite_id' => ['required', 'string', 'exists:types_activites,uuid'],
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'lieu' => ['nullable', 'string', 'max:255'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'heure_debut' => ['nullable', 'date_format:H:i'],
            'heure_fin' => ['nullable', 'date_format:H:i'],
            'statut' => ['nullable', 'string', 'in:planifiee,en_cours,terminee,annulee'],
            'sections_uuids' => ['nullable', 'array'],
            'niveaux_uuids' => ['nullable', 'array'],
            'classes_uuids' => ['nullable', 'array'],
            'animateurs_uuids' => ['nullable', 'array'],
        ];
    }
}
