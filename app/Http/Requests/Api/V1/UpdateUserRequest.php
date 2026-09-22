<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userModel = $this->route('user');
        $userId = $userModel ? $userModel->id : null;

        return [
            'profil_id'  => ['sometimes', 'nullable'],
            'name'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'nom'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'prenoms'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'email'      => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password'   => ['nullable', 'string', 'min:6'],
            'telephone'  => ['nullable', 'string', 'max:30'],
            'statut'     => ['sometimes', 'nullable', 'string', 'in:actif,inactif,Actif,Inactif,suspendu,Suspendu'],
            'is_active'  => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email'  => "L'adresse email est invalide.",
            'email.unique' => 'Cette adresse email est déjà utilisée par un autre utilisateur.',
            'password.min' => 'Le mot de passe doit comporter au moins 6 caractères.',
        ];
    }
}
