<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'profil_id'  => ['required'],
            'name'       => ['nullable', 'string', 'max:255'],
            'nom'        => ['nullable', 'string', 'max:255'],
            'prenoms'    => ['nullable', 'string', 'max:255'],
            'email'      => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password'   => ['required', 'string', 'min:6'],
            'telephone'  => ['nullable', 'string', 'max:30'],
            'statut'     => ['nullable', 'string', 'in:actif,inactif,Actif,Inactif'],
            'is_active'  => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'profil_id.required' => 'Le profil utilisateur est obligatoire.',
            'email.required'     => "L'adresse email est obligatoire.",
            'email.email'        => "L'adresse email est invalide.",
            'email.unique'       => 'Cette adresse email est déjà utilisée.',
            'password.required'  => 'Le mot de passe est obligatoire.',
            'password.min'       => 'Le mot de passe doit comporter au moins 6 caractères.',
        ];
    }
}
