<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => ['sometimes', 'required', 'string', 'max:255'],
            'libelle' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'statut' => ['sometimes', 'required', 'string', 'in:actif,inactif'],
            'permissions' => ['nullable', 'array'],
        ];
    }
}
