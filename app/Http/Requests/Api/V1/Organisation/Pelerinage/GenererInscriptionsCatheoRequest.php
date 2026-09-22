<?php

namespace App\Http\Requests\Api\V1\Organisation\Pelerinage;

use Illuminate\Foundation\Http\FormRequest;

class GenererInscriptionsCatheoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tarif_pelerinage_id' => 'nullable|integer',
            'niveau_id'           => 'nullable|integer',
            'classe_id'           => 'nullable|integer',
        ];
    }
}
