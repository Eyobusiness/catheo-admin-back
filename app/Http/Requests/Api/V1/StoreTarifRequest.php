<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreTarifRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'annee_catechese_id' => ['required', 'string', 'exists:annee_catecheses,uuid'],
            'niveau_id' => ['nullable', 'string', 'exists:niveaux,uuid'],
            'niveau_ids' => ['nullable', 'array'],
            'niveau_ids.*' => ['string', 'exists:niveaux,uuid'],
            'intitule' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'montant' => ['required', 'numeric', 'min:0'],
            'est_obligatoire' => ['nullable', 'boolean'],
            'type_tarif' => ['required', 'string'],
        ];
    }
}