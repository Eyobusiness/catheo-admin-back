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
            'niveau_id' => ['required', 'string', 'exists:niveaux,uuid'],
            'classe_id' => ['nullable', 'string', 'exists:classes,uuid'],
            'notes_validation' => ['nullable', 'string'],
        ];
    }
}
