<?php

namespace App\Http\Requests\Api\V1\Profil;

use Illuminate\Foundation\Http\FormRequest;

class StoreProfilRequest extends FormRequest
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
            'nom' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:profils,code'],
            'description' => ['nullable', 'string'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ];
    }

    /**
     * Messages de validation.
     */
    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom du profil est obligatoire.',
            'code.required' => 'Le code du profil est obligatoire.',
            'code.unique' => 'Ce code de profil existe déjà.',
            'permissions.array' => 'Les permissions doivent être un tableau de chaînes.',
        ];
    }
}
