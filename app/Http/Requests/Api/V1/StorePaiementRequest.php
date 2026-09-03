<?php

namespace App\Http\Requests\Api\V1;

use App\Models\AnneeCatechese;
use App\Models\Catechumene;
use App\Models\InscriptionAnnuelle;
use App\Models\Tarif;
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
            'annee_catechese_id' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (empty($value)) {
                        $fail("L'année de catéchèse est obligatoire.");
                        return;
                    }
                    $exists = is_numeric($value)
                        ? AnneeCatechese::where('id', $value)->exists()
                        : AnneeCatechese::where('uuid', $value)->exists();
                    if (!$exists) {
                        $fail("L'année de catéchèse sélectionnée est introuvable.");
                    }
                },
            ],
            'inscription_annuelle_id' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if (empty($value) || $value === 'null' || $value === 'undefined') {
                        return;
                    }
                    $exists = is_numeric($value)
                        ? InscriptionAnnuelle::where('id', $value)->exists()
                        : InscriptionAnnuelle::where('uuid', $value)->exists();
                    if (!$exists) {
                        $fail("L'inscription annuelle sélectionnée est introuvable.");
                    }
                },
            ],
            'catechumene_id' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if (empty($value) || $value === 'null' || $value === 'undefined') {
                        return;
                    }
                    $exists = is_numeric($value)
                        ? Catechumene::where('id', $value)->exists()
                        : Catechumene::where('uuid', $value)->exists();
                    if (!$exists) {
                        $fail("Le catéchumène sélectionné est introuvable.");
                    }
                },
            ],
            'operation_paiement_id' => ['nullable'],
            'mode_paiement' => ['required', 'string'],
            'reference_transaction' => ['nullable', 'string', 'max:255'],
            'date_paiement' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.operation_paiement_id' => ['nullable'],
            'lignes.*.tarif_id' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if (empty($value) || $value === 'null' || $value === 'undefined') {
                        return;
                    }
                    $exists = is_numeric($value)
                        ? Tarif::where('id', $value)->exists()
                        : Tarif::where('uuid', $value)->exists();
                    if (!$exists) {
                        $fail("Le tarif sélectionné est introuvable.");
                    }
                },
            ],
            'lignes.*.designation' => ['required', 'string', 'max:255'],
            'lignes.*.montant' => ['required', 'numeric', 'min:0'],
            'lignes.*.quantite' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
