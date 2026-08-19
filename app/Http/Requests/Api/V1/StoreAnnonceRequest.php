<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnnonceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'annee_catechese_id' => ['required', 'string', 'exists:annee_catecheses,uuid'],
            'titre' => ['required', 'string', 'max:255'],
            'contenu' => ['required', 'string'],
            'cible' => ['required', 'string', 'in:tous,parents,animateurs,section,niveau,classe'],
            'section_id' => ['nullable', 'string', 'exists:sections,uuid'],
            'niveau_id' => ['nullable', 'string', 'exists:niveaux,uuid'],
            'classe_id' => ['nullable', 'string', 'exists:classes,uuid'],
            'date_publication' => ['required', 'date'],
            'date_expiration' => ['nullable', 'date', 'after_or_equal:date_publication'],
            'statut' => ['nullable', 'string', 'in:brouillon,publiee,archivee'],
        ];
    }
}
