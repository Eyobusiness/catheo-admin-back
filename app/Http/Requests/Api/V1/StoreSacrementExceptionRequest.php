<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreSacrementExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'catechumene_id'     => ['nullable'],
            'catechumeneId'      => ['nullable'],
            'sacrement_id'       => ['nullable'],
            'sacrement_type'     => ['nullable', 'string'],
            'sacrementType'      => ['nullable', 'string'],
            'annee_catechese_id' => ['nullable'],
            'motif'              => ['required', 'string', 'max:150'],
            'autorise_par'       => ['nullable', 'string', 'max:150'],
            'autorisePar'        => ['nullable', 'string', 'max:150'],
            'observation'        => ['nullable', 'string', 'max:1000'],
            'date_derogation'    => ['nullable', 'date'],
            'dateAjout'          => ['nullable', 'date'],
            'statut'             => ['nullable', 'string', 'max:30'],
        ];
    }
}
