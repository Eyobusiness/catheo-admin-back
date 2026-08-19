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
            'email' => ['required', 'email', 'exists:users,email'],
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
