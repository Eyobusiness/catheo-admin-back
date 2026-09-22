<?php

namespace App\Http\Requests\Api\V1\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProduitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'        => 'sometimes|string|max:50',
            'nom'         => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'icone'       => 'nullable|string|max:100',
            'statut'      => 'sometimes|string|in:actif,inactif',
        ];
    }
}
