<?php

namespace App\Http\Requests\Api\V1\Organisation;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'montant'                => 'required|numeric|min:1',
            'libelle'                => 'required|string|max:255',
            'mode_reglement'         => 'required|string|in:especes,mobile_money,virement,cheque,carte_bancaire',
            'date_operation'         => 'nullable|date',
            'campagne_pelerinage_id' => 'nullable|integer|exists:campagne_pelerinages,id',
            'beneficiaire'           => 'nullable|string|max:255',
            'observation'            => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'montant.required'        => 'Le montant de la dépense est obligatoire.',
            'montant.numeric'         => 'Le montant doit être une valeur numérique.',
            'montant.min'             => 'Le montant doit être supérieur à zéro.',
            'libelle.required'        => 'Le motif ou libellé de la dépense est obligatoire.',
            'mode_reglement.required' => 'Le mode de règlement est obligatoire.',
            'mode_reglement.in'       => 'Le mode de règlement sélectionné est invalide.',
        ];
    }
}
