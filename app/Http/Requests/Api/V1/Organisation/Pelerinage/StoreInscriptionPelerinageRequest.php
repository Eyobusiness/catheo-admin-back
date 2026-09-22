<?php

namespace App\Http\Requests\Api\V1\Organisation\Pelerinage;

use App\Models\InscriptionPelerinage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInscriptionPelerinageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('taille') && is_string($this->taille)) {
            $this->merge([
                'taille' => strtoupper(trim($this->taille)),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'tarif_pelerinage_id'       => 'required|integer',
            'type_participant'          => ['nullable', 'string', Rule::in([InscriptionPelerinage::TYPE_CATECHUMENE, InscriptionPelerinage::TYPE_EXTERNE])],
            'catechumene_id'            => 'nullable|integer',
            'nom'                       => 'required_if:type_participant,EXTERNE|nullable|string|max:255',
            'prenoms'                   => 'required_if:type_participant,EXTERNE|nullable|string|max:255',
            'sexe'                      => 'nullable|string|in:M,F',
            'taille'                    => ['nullable', 'string', 'max:10', Rule::in(InscriptionPelerinage::TAILLES)],
            'date_naissance'            => 'nullable|date',
            'telephone'                 => 'nullable|string|max:30',
            'email'                     => 'nullable|email|max:255',
            'adresse'                   => 'nullable|string|max:255',
            'contact_urgence_nom'       => 'nullable|string|max:255',
            'contact_urgence_telephone' => 'nullable|string|max:30',
            'observation'               => 'nullable|string',
        ];
    }
}
