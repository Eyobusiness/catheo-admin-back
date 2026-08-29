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
            'annee_catechese_id' => ['nullable', 'string'],
            'classe_id' => ['required', 'string'],
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'date_seance' => ['required', 'date'],
            'heure_debut' => ['nullable', 'string'],
            'heure_fin' => ['nullable', 'string'],
            'statut' => ['nullable', 'string', 'in:planifiee,effectuee,annulee,Planifiée,Effectuée,Annulée'],
        ];
    }
}
