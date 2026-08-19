<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreSeanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'annee_catechese_id' => ['required', 'string', 'exists:annee_catecheses,uuid'],
            'classe_id' => ['required', 'string', 'exists:classes,uuid'],
            'module_trimestriel_id' => ['nullable', 'string', 'exists:modules_trimestriels,uuid'],
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'date_seance' => ['required', 'date'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
            'statut' => ['nullable', 'string', 'in:planifiee,effectuee,annulee'],
        ];
    }
}
