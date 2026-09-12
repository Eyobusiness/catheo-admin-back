<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $email = strtolower(trim((string) $value));
                    $existsInUsers = \App\Models\User::where('email', $email)->exists();
                    $existsInAnimateurs = \App\Models\Animateur::where('email', $email)->exists();

                    if (!$existsInUsers && !$existsInAnimateurs) {
                        $fail('Aucun compte n\'est associé à cette adresse email.');
                    }
                },
            ],
            'code'                  => ['required', 'string', 'size:6'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'                 => 'L\'adresse email est obligatoire.',
            'email.email'                    => 'L\'adresse email doit être valide.',
            'email.exists'                   => 'Aucun compte n\'est associé à cette adresse email.',
            'code.required'                  => 'Le code de vérification est obligatoire.',
            'code.size'                      => 'Le code de vérification doit comporter exactement 6 chiffres.',
            'password.required'              => 'Le mot de passe est obligatoire.',
            'password.min'                   => 'Le mot de passe doit contenir au moins :min caractères.',
            'password.confirmed'            => 'La confirmation du mot de passe ne correspond pas.',
            'password_confirmation.required' => 'La confirmation du mot de passe est obligatoire.',
        ];
    }
}
