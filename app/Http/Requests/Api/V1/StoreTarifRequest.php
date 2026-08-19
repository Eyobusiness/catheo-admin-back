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
            'intitule' => ['required', 'string', 'max:255'],
            'montant' => ['required', 'numeric', 'min:0'],
            'type_tarif' => ['required', 'string', 'in:inscription,manuel,uniforme,examen,autre'],
        ];
    }
}
