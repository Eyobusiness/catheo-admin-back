<?php

namespace App\Http\Requests\Api\V1\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $user = $this->route('user');
        $userId = is_object($user) ? $user->id : null;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'telephone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8'],
            'profil_uuid' => ['sometimes', 'required', 'string', 'exists:profils,uuid'],
            'paroisse_uuid' => ['nullable', 'string', 'exists:paroisse_configurations,uuid'],
            'statut' => ['sometimes', 'required', 'string', 'in:actif,suspendu,inactif'],
        ];
    }

    /**
     * Messages de validation.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom ne peut pas être vide.',
            'email.required' => 'L\'adresse email ne peut pas être vide.',
            'email.unique' => 'Cette adresse email est déjà attribuée.',
            'password.min' => 'Le mot de passe doit faire au moins 8 caractères.',
            'profil_uuid.exists' => 'Le profil sélectionné n\'existe pas.',
            'paroisse_uuid.exists' => 'La paroisse sélectionnée n\'existe pas.',
        ];
    }
}
