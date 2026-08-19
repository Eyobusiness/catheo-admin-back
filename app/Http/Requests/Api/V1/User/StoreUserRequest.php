<?php

namespace App\Http\Requests\Api\V1\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'profil_uuid' => ['required', 'string', 'exists:profils,uuid'],
            'paroisse_uuid' => ['nullable', 'string', 'exists:paroisse_configurations,uuid'],
            'statut' => ['nullable', 'string', 'in:actif,suspendu,inactif'],
        ];
    }

    /**
     * Messages de validation.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom complet est obligatoire.',
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'L\'adresse email doit être valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'profil_uuid.required' => 'Le profil utilisateur est obligatoire.',
            'profil_uuid.exists' => 'Le profil sélectionné n\'existe pas.',
            'paroisse_uuid.exists' => 'La paroisse sélectionnée n\'existe pas.',
        ];
    }
}
