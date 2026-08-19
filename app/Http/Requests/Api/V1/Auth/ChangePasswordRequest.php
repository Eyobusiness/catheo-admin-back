<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password'      => ['required', 'string'],
            'password'              => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required'      => 'Le mot de passe actuel est obligatoire.',
            'password.required'              => 'Le nouveau mot de passe est obligatoire.',
            'password.min'                   => 'Le nouveau mot de passe doit contenir au moins :min caractères.',
            'password.confirmed'            => 'La confirmation du nouveau mot de passe ne correspond pas.',
            'password.different'            => 'Le nouveau mot de passe doit être différent du mot de passe actuel.',
            'password_confirmation.required' => 'La confirmation du mot de passe est obligatoire.',
        ];
    }
}
