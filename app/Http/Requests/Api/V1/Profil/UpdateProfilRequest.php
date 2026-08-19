<?php

namespace App\Http\Requests\Api\V1\Profil;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfilRequest extends FormRequest
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
        $profil = $this->route('profil');
        $profilId = is_object($profil) ? $profil->id : null;

        return [
            'nom' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('profils', 'code')->ignore($profilId),
            ],
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
            'nom.required' => 'Le nom du profil ne peut pas être vide.',
            'code.required' => 'Le code du profil ne peut pas être vide.',
            'code.unique' => 'Ce code de profil est déjà utilisé par un autre profil.',
            'permissions.array' => 'Les permissions doivent être un tableau de chaînes.',
        ];
    }
}
