<?php

namespace App\Http\Requests\Api\V1\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class StorePaiementAbonnementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('echeance_abonnement_id') && !is_numeric($this->echeance_abonnement_id)) {
            $id = \App\Models\EcheanceAbonnement::where('uuid', $this->echeance_abonnement_id)->value('id');
            if ($id) {
                $this->merge(['echeance_abonnement_id' => $id]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'echeance_abonnement_id' => 'required|exists:echeances_abonnement,id',
            'montant'                => 'required|numeric|min:0.01',
            'devise'                 => 'nullable|string|max:10',
            'mode_paiement'          => 'required|string|in:especes,virement,mobile_money,cheque,autre',
            'date_paiement'          => 'required|date',
            'reference_transaction'  => 'nullable|string|max:100',
            'observation'            => 'nullable|string',
        ];
    }
}
