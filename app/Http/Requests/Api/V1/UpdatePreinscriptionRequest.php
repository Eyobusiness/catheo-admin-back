<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreinscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'campagne_id'               => ['nullable'],
            'campagne_preinscription_id'=> ['nullable'],
            'annee_catechese_id'        => ['nullable'],
            'section_souhaite_id'       => ['nullable'],
            'section_id'                => ['nullable'],
            'niveau_souhaite_id'        => ['nullable'],
            'niveau_id'                 => ['nullable'],
            'type_demande'              => ['nullable', 'string', 'in:nouvelle_inscription,reinscription,premiere_inscription'],
            
            // Fiche Identité
            'nom'                       => ['sometimes', 'required', 'string', 'max:255'],
            'prenoms'                   => ['sometimes', 'required', 'string', 'max:255'],
            'sexe'                      => ['sometimes', 'required', 'string', 'in:M,F,m,f'],
            'date_naissance'            => ['sometimes', 'required', 'date'],
            'lieu_naissance'            => ['nullable', 'string', 'max:255'],
            'adresse'                   => ['nullable', 'string'],
            'telephone'                 => ['nullable', 'string', 'max:30'],
            'photo_url'                 => ['nullable', 'string', 'max:500'],
            'situation_matrimoniale'    => ['nullable', 'string', 'max:100'],

            // Informations Parents / Tuteur
            'nom_pere'                  => ['nullable', 'string', 'max:255'],
            'telephone_pere'            => ['nullable', 'string', 'max:30'],
            'nom_mere'                  => ['nullable', 'string', 'max:255'],
            'telephone_mere'            => ['nullable', 'string', 'max:30'],
            'nom_tuteur'                => ['nullable', 'string', 'max:255'],
            'telephone_tuteur'          => ['nullable', 'string', 'max:30'],

            // Baptême & Sacrements
            'est_baptise'               => ['nullable', 'boolean'],
            'date_bapteme'              => ['nullable', 'date'],
            'lieu_bapteme'              => ['nullable', 'string', 'max:255'],
            'paroisse_bapteme'          => ['nullable', 'string', 'max:255'],

            // Parrain / Marraine
            'nom_parrain'               => ['nullable', 'string', 'max:255'],
            'sexe_parrain'              => ['nullable', 'string', 'in:M,F,m,f'],
            'telephone_parrain'         => ['nullable', 'string', 'max:30'],

            // Documents joints
            'acte_naissance_url'        => ['nullable', 'string', 'max:500'],

            // Statut et validation
            'statut'                    => ['nullable', 'string', 'in:en_attente,validee,rejetee,valide,rejete'],
            'notes_validation'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}
