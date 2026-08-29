<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampagnePreinscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'annee_catechese_id' => ['nullable', 'string'],
            'nom'                => ['nullable', 'string', 'max:255'],
            'titre'              => ['nullable', 'string', 'max:255'],
            'date_debut'         => ['required', 'date'],
            'date_fin'           => ['required', 'date'],
            'sections_autorisees'=> ['nullable', 'array'],
            'statut'             => ['nullable', 'string'],
            'description'        => ['nullable', 'string'],
        ];
    }
}

