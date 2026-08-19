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
            'profil_id' => ['required', 'string', 'exists:profils,uuid'],
            'name' => ['nullable', 'string', 'max:255'],
            'nom' => ['nullable', 'string', 'max:255'],
            'prenoms' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
            'statut' => ['nullable', 'string'],
        ];
    }
}
