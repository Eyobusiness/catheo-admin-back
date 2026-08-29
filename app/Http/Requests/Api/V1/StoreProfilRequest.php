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
            'code'             => ['required', 'string', 'max:50', 'unique:profils,code'],
            'nom'              => ['nullable', 'string', 'max:255'],
            'libelle'          => ['nullable', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'statut'           => ['nullable', 'string', 'in:actif,inactif,Actif,Inactif'],
            'permissions'      => ['nullable', 'array'],
            'menu_permissions' => ['nullable', 'array'],
            'menus'            => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Le code du profil est obligatoire.',
            'code.unique'   => 'Ce code de profil est déjà utilisé.',
            'nom.max'       => 'Le nom ne peut pas dépasser 255 caractères.',
        ];
    }
}
