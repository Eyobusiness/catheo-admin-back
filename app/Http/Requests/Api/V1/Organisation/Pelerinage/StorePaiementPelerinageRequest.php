<?php

namespace App\Http\Requests\Api\V1\Organisation\Pelerinage;

use Illuminate\Foundation\Http\FormRequest;

class StorePaiementPelerinageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'montant'               => 'required|numeric|min:0.01',
            'mode_paiement'         => 'required|string|max:50',
            'date_paiement'         => 'nullable|date',
            'reference_transaction' => 'nullable|string|max:100',
            'observation'           => 'nullable|string',
        ];
    }
}
