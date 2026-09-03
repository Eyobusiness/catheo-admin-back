<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCatechumeneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ceb_id'                       => ['nullable', 'string', 'exists:cebs,uuid'],
            'nom'                          => ['sometimes', 'required', 'string', 'max:255'],
            'prenoms'                      => ['sometimes', 'required', 'string', 'max:255'],
            'sexe'                         => ['sometimes', 'required', 'string', 'in:M,F'],
            'date_naissance'               => ['nullable', 'date'],
            'lieu_naissance'               => ['nullable', 'string', 'max:255'],
            'adresse'                      => ['nullable', 'string'],
            'domicile'                     => ['nullable', 'string', 'max:255'],
            'profession'                   => ['nullable', 'string', 'max:255'],
            'classe_scolaire'              => ['nullable', 'string', 'max:100'],
            'situation_matrimoniale'       => ['nullable', 'string', 'max:100'],
            'telephone'                    => ['nullable', 'string', 'max:30'],
            'photo_path'                   => ['nullable', 'string'],
            'photo_url'                    => ['nullable', 'string'],
            'photo'                        => ['nullable', 'string'],
            
            // Filiation & Tuteurs
            'nom_pere'                     => ['nullable', 'string', 'max:255'],
            'origine_pere'                 => ['nullable', 'string', 'max:255'],
            'telephone_pere'               => ['nullable', 'string', 'max:30'],
            'nom_mere'                     => ['nullable', 'string', 'max:255'],
            'origine_mere'                 => ['nullable', 'string', 'max:255'],
            'telephone_mere'               => ['nullable', 'string', 'max:30'],
            'nom_tuteur'                   => ['nullable', 'string', 'max:255'],
            'telephone_tuteur'             => ['nullable', 'string', 'max:30'],
            
            // Sacrements
            'est_baptise'                  => ['nullable', 'boolean'],
            'num_carnet_bapteme'           => ['nullable', 'string', 'max:100'],
            'date_bapteme'                 => ['nullable', 'date'],
            'lieu_bapteme'                 => ['nullable', 'string', 'max:255'],
            'diocese_bapteme'              => ['nullable', 'string', 'max:255'],
            'ville_bapteme'                => ['nullable', 'string', 'max:255'],
            'paroisse_bapteme'             => ['nullable', 'string', 'max:255'],
            'date_premiere_communion'      => ['nullable', 'date'],
            'paroisse_premiere_communion'  => ['nullable', 'string', 'max:255'],
            'date_confirmation'            => ['nullable', 'date'],
            'paroisse_confirmation'        => ['nullable', 'string', 'max:255'],
            'ministre_confirmation'        => ['nullable', 'string', 'max:255'],
            
            'statut'                       => ['nullable', 'string', 'in:actif,abandon,transfere,complete'],
        ];
    }
}
