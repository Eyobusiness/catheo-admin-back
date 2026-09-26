<?php

namespace App\Http\Requests\Api\V1\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrganisationDirectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type_organisation'     => ['required', 'string', 'in:OPPE,OPPJ,OPPA'],
            'nom'                   => ['required', 'string', 'max:255'],
            'description'           => ['nullable', 'string'],
            'paroisse_id'           => ['nullable', 'string', 'exists:paroisse_configurations,uuid'],
            'independant'           => ['nullable', 'boolean'],
            'telephone'             => ['nullable', 'string', 'max:30'],
            'email'                 => ['nullable', 'email', 'max:150'],
            'adresse'               => ['nullable', 'string', 'max:500'],
            'responsable_nom'       => ['nullable', 'string', 'max:255'],
            'responsable_telephone' => ['nullable', 'string', 'max:30'],
            'responsable_email'     => ['nullable', 'email', 'max:150'],
            'logo'                  => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'type_organisation.required' => 'Le type d\'organisation (OPPE, OPPJ, OPPA) est obligatoire.',
            'type_organisation.in'       => 'Le type doit être OPPE, OPPJ ou OPPA.',
            'nom.required'               => 'Le nom de l\'organisation est obligatoire.',
            'paroisse_id.exists'         => 'La paroisse sélectionnée est introuvable.',
        ];
    }
}