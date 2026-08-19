<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnnonceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titre' => ['sometimes', 'required', 'string', 'max:255'],
            'contenu' => ['sometimes', 'required', 'string'],
            'cible' => ['sometimes', 'required', 'string', 'in:tous,parents,animateurs,section,niveau,classe'],
            'date_publication' => ['sometimes', 'required', 'date'],
            'date_expiration' => ['nullable', 'date'],
            'statut' => ['nullable', 'string', 'in:brouillon,publiee,archivee'],
        ];
    }
}
