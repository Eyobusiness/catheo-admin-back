<?php

namespace App\Http\Requests\Api\V1\Organisation\Pelerinage;

use Illuminate\Foundation\Http\FormRequest;

class StoreTarifPelerinageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'        => 'nullable|string|max:50',
            'libelle'     => 'required|string|max:255',
            'description' => 'nullable|string',
            'montant'     => 'required|numeric|min:0',
            'devise'      => 'nullable|string|size:3',
            'statut'      => 'nullable|string|in:actif,inactif',
        ];
    }
}
