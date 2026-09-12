<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyResetCodeRequest extends FormRequest
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
            'code'  => ['required', 'string', 'size:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email'    => 'L\'adresse email doit être valide.',
            'email.exists'   => 'Aucun compte n\'est associé à cette adresse email.',
            'code.required'  => 'Le code de vérification est obligatoire.',
            'code.size'      => 'Le code de vérification doit comporter exactement 6 chiffres.',
        ];
    }
}
