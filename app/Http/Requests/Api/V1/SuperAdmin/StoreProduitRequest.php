<?php

namespace App\Http\Requests\Api\V1\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProduitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'        => 'required|string|max:50|unique:produits,code',
            'nom'         => 'required|string|max:255',
            'description' => 'nullable|string',
            'icone'       => 'nullable|string|max:100',
            'statut'      => 'nullable|string|in:actif,inactif',
        ];
    }
}
