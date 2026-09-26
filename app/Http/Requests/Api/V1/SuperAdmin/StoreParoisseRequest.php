<?php

namespace App\Http\Requests\Api\V1\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class StoreParoisseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom_paroisse'     => ['required', 'string', 'max:255'],
            'code_paroisse'    => ['required', 'string', 'max:20', 'unique:paroisse_configurations,code_paroisse', 'regex:/^[A-Za-z0-9_-]+$/'],
            'diocese'          => ['required', 'string', 'max:150'],
            'doyenne'          => ['nullable', 'string', 'max:150'],
            'ville'            => ['nullable', 'string', 'max:100'],
            'commune'          => ['nullable', 'string', 'max:100'],
            'telephone'        => ['nullable', 'string', 'max:30'],
            'email'            => ['nullable', 'email', 'max:150'],
            'adresse'          => ['nullable', 'string', 'max:500'],
            'cure_nom'         => ['nullable', 'string', 'max:255'],
            'coordination_nom' => ['nullable', 'string', 'max:255'],
            'logo_paroisse'    => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'logo_catechese'   => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom_paroisse.required'  => 'Le nom de la paroisse est obligatoire.',
            'code_paroisse.required' => 'Le code de la paroisse est obligatoire.',
            'code_paroisse.unique'   => 'Ce code paroisse est déjà utilisé.',
            'code_paroisse.regex'    => 'Le code doit contenir uniquement des lettres majuscules et des chiffres.',
            'diocese.required'       => 'Le diocèse est obligatoire.',
        ];
    }
}