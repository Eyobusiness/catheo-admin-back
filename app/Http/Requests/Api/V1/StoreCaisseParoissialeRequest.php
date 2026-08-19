<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreCaisseParoissialeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'annee_catechese_id' => ['required', 'string', 'exists:annee_catecheses,uuid'],
            'type_mouvement' => ['required', 'string', 'in:entree,sortie'],
            'categorie' => ['required', 'string', 'in:inscription,don,cotisation,depense_fournitures,depense_evenement,autre'],
            'montant' => ['required', 'numeric', 'min:0.01'],
            'reference_document' => ['nullable', 'string', 'max:255'],
            'libelle' => ['required', 'string', 'max:255'],
            'date_mouvement' => ['required', 'date'],
        ];
    }
}
