<?php

namespace App\Http\Requests\Api\V1\Organisation\Pelerinage;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTarifPelerinageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'        => 'sometimes|nullable|string|max:50',
            'libelle'     => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'montant'     => 'sometimes|required|numeric|min:0',
            'devise'      => 'nullable|string|size:3',
            'statut'      => 'nullable|string|in:actif,inactif',
        ];
    }
}
