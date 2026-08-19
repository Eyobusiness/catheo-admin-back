<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreTypeActiviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'couleur_agenda' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'statut' => ['nullable', 'string', 'in:actif,inactif'],
        ];
    }
}
