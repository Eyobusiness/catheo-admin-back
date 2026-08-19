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
            'titre' => ['sometimes', 'required', 'string', 'max:255'],
            'nom' => ['nullable', 'string', 'max:255'],
            'date_debut' => ['sometimes', 'required', 'date'],
            'date_fin' => ['sometimes', 'required', 'date', 'after_or_equal:date_debut'],
            'sections_autorisees' => ['nullable', 'array'],
            'statut' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ];
    }
}
