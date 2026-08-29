<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreCatechumenSacrementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sacrement_id'        => ['required'],
            'annee_catechese_id'  => ['nullable'],
            'statut'              => ['nullable', 'string', 'in:preparation,valide,en_preparation'],
            'date_sacrement'      => ['nullable', 'date'],
            'lieu'                => ['nullable', 'string', 'max:255'],
            'paroisse_nom'        => ['nullable', 'string', 'max:255'],
            'celebrant'           => ['nullable', 'string', 'max:255'],
            'numero_registre'     => ['nullable', 'string', 'max:100'],
            'num_carnet'          => ['nullable', 'string', 'max:100'],
            'observations'        => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'sacrement_id.required' => 'Le type de sacrement est obligatoire.',
            'date_sacrement.date'   => 'La date du sacrement doit être une date valide.',
            'statut.in'             => "Le statut doit être 'preparation' ou 'valide'.",
        ];
    }
}
