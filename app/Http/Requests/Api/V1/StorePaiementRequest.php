<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StorePaiementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'annee_catechese_id' => ['required', 'string', 'exists:annee_catecheses,uuid'],
            'inscription_annuelle_id' => ['nullable', 'string', 'exists:inscriptions_annuelles,uuid'],
            'catechumene_id' => ['nullable', 'string', 'exists:catechumenes,uuid'],
            'mode_paiement' => ['required', 'string', 'in:especes,mobile_money,cheque,virement'],
            'reference_transaction' => ['nullable', 'string', 'max:255'],
            'date_paiement' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.tarif_id' => ['nullable', 'string', 'exists:tarifs,uuid'],
            'lignes.*.designation' => ['required', 'string', 'max:255'],
            'lignes.*.montant' => ['required', 'numeric', 'min:0'],
            'lignes.*.quantite' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
