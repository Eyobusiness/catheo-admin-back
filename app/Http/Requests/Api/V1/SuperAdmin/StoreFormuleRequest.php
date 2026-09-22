<?php

namespace App\Http\Requests\Api\V1\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class StoreFormuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('produit_id') && !is_numeric($this->produit_id)) {
            $id = \App\Models\Produit::where('uuid', $this->produit_id)->orWhere('code', $this->produit_id)->value('id');
            if ($id) {
                $this->merge(['produit_id' => $id]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'produit_id'   => 'required|exists:produits,id',
            'code'         => 'required|string|max:50',
            'nom'          => 'required|string|max:255',
            'description'  => 'nullable|string',
            'periodicite'  => 'required|string|in:mensuelle,annuelle',
            'montant'      => 'nullable|numeric|min:0',
            'devise'       => 'nullable|string|max:10',
            'est_gratuite' => 'nullable|boolean',
            'statut'       => 'nullable|string|in:actif,inactif',
            'ordre'        => 'nullable|integer|min:0',
        ];
    }
}
