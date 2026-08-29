<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCampagnePreinscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'annee_catechese_id' => ['nullable', 'string'],
            'titre'              => ['nullable', 'string', 'max:255'],
            'nom'                => ['nullable', 'string', 'max:255'],
            'date_debut'         => ['nullable', 'date'],
            'date_fin'           => ['nullable', 'date'],
            'sections_autorisees'=> ['nullable', 'array'],
            'statut'             => ['nullable', 'string'],
            'description'        => ['nullable', 'string'],
        ];
    }
}

