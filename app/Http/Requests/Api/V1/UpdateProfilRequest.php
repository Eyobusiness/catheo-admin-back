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
            'nom'              => ['sometimes', 'nullable', 'string', 'max:255'],
            'libelle'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'statut'           => ['sometimes', 'nullable', 'string', 'in:actif,inactif,Actif,Inactif'],
            'permissions'      => ['nullable', 'array'],
            'menu_permissions' => ['nullable', 'array'],
            'menus'            => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.max' => 'Le nom ne peut pas dépasser 255 caractères.',
        ];
    }
}
