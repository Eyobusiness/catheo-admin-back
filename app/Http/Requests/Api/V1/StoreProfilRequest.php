<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreProfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:profils,code'],
            'nom' => ['nullable', 'string', 'max:255'],
            'libelle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'statut' => ['nullable', 'string', 'in:actif,inactif'],
            'permissions' => ['nullable', 'array'],
        ];
    }
}
