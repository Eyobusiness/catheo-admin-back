<?php

namespace App\Http\Requests\Api\V1\Organisation\Pelerinage;

use App\Models\InscriptionPelerinage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInscriptionPelerinageRequest extends FormRequest
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
            'tarif_pelerinage_id'       => 'sometimes|required|integer',
            'nom'                       => 'sometimes|required|string|max:255',
            'prenoms'                   => 'sometimes|required|string|max:255',
            'sexe'                      => 'nullable|string|in:M,F',
            'taille'                    => ['nullable', 'string', 'max:10', Rule::in(InscriptionPelerinage::TAILLES)],
            'date_naissance'            => 'nullable|date',
            'telephone'                 => 'nullable|string|max:30',
            'email'                     => 'nullable|email|max:255',
            'adresse'                   => 'nullable|string|max:255',
            'contact_urgence_nom'       => 'nullable|string|max:255',
            'contact_urgence_telephone' => 'nullable|string|max:30',
            'statut_inscription'        => ['nullable', 'string', Rule::in(InscriptionPelerinage::STATUTS)],
            'statut_participation'      => ['nullable', 'string', Rule::in(InscriptionPelerinage::PARTICIPATIONS)],
            'badge_imprime'             => 'nullable|boolean',
            'kit_remis'                 => 'nullable|boolean',
            'observation'               => 'nullable|string',
        ];
    }
}
