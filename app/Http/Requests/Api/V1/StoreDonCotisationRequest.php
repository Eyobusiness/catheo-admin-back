<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreDonCotisationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'annee_catechese_id' => ['required', 'string', 'exists:annee_catecheses,uuid'],
            'mouvement_id' => ['nullable', 'string', 'exists:mouvements,uuid'],
            'ceb_id' => ['nullable', 'string', 'exists:cebs,uuid'],
            'donateur_nom' => ['required', 'string', 'max:255'],
            'type_don' => ['required', 'string', 'in:don_especes,don_nature,cotisation_ceb,cotisation_mouvement'],
            'montant' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'date_reception' => ['required', 'date'],
        ];
    }
}
