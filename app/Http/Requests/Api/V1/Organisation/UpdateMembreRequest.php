<?php

namespace App\Http\Requests\Api\V1\Organisation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMembreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom'             => 'sometimes|required|string|max:100',
            'prenoms'         => 'sometimes|required|string|max:150',
            'sexe'            => 'sometimes|required|string|in:M,F',
            'date_naissance'  => 'nullable|date',
            'telephone'       => 'nullable|string|max:30',
            'email'           => 'nullable|email|max:150',
            'quartier'        => 'nullable|string|max:150',
            'adresse'         => 'nullable|string',
            'fonction'        => 'nullable|string|max:100',
            'date_entree'     => 'nullable|date',
            'statut'          => 'sometimes|required|string|in:actif,inactif,suspendu',
            'photo_path'      => 'nullable|string|max:255',
            'observation'     => 'nullable|string',
        ];
    }
}
