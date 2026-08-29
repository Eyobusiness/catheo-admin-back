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
            'annee_catechese_id' => ['nullable', 'string'],
            'titre'              => ['sometimes', 'string', 'max:500'],
            'contenu'            => ['sometimes', 'string'],
            'cible'              => ['nullable', 'string'],
            'cible_type'         => ['nullable', 'string', 'max:100'],
            'cible_id'           => ['nullable', 'string'],
            'cible_ids'          => ['nullable', 'array'],
            'cible_nom'          => ['nullable', 'string'],
            'section_id'         => ['nullable', 'string'],
            'niveau_id'          => ['nullable', 'string'],
            'classe_id'          => ['nullable', 'string'],
            'ceb_id'             => ['nullable', 'string'],
            'mouvement_id'       => ['nullable', 'string'],
            'canal'              => ['nullable', 'string', 'max:50'],
            'date_publication'   => ['nullable', 'string'],
            'date_diffusion'     => ['nullable', 'string'],
            'heure_diffusion'    => ['nullable', 'string', 'max:20'],
            'date_expiration'    => ['nullable', 'string'],
            'priorite'           => ['nullable', 'string', 'max:50'],
            'statut'             => ['nullable', 'string', 'max:50'],
        ];
    }
}
