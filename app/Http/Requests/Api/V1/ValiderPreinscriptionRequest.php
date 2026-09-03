<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ValiderPreinscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'niveau_id'        => ['required', 'string'],
            'classe_id'        => ['nullable', 'string'],
            'catechumene_id'   => ['nullable', 'string'],
            'tarif_id'         => ['nullable', 'string'],
            'frais_payes'      => ['nullable', 'boolean'],
            'notes_validation' => ['nullable', 'string'],
        ];
    }
}
