<?php

namespace App\Http\Requests\Api\V1\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFormuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'         => 'sometimes|string|max:50',
            'nom'          => 'sometimes|string|max:255',
            'description'  => 'nullable|string',
            'periodicite'  => 'sometimes|string|in:mensuelle,annuelle',
            'montant'      => 'nullable|numeric|min:0',
            'devise'       => 'nullable|string|max:10',
            'est_gratuite' => 'nullable|boolean',
            'statut'       => 'sometimes|string|in:actif,inactif',
            'ordre'        => 'nullable|integer|min:0',
        ];
    }
}
