<?php

namespace App\Http\Requests\Api\V1\Organisation\Pelerinage;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampagnePelerinageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom'                    => 'required|string|max:255',
            'description'            => 'nullable|string',
            'activite_id'            => 'nullable|integer',
            'lieu_depart'            => 'required|string|max:255',
            'destination'            => 'required|string|max:255',
            'date_depart'            => 'required|date',
            'heure_depart'           => 'nullable|date_format:H:i',
            'date_fin'               => 'required|date|after_or_equal:date_depart',
            'heure_fin'              => 'nullable|date_format:H:i',
            'date_debut_inscription' => 'nullable|date',
            'date_fin_inscription'   => 'nullable|date|after_or_equal:date_debut_inscription',
            'capacite'               => 'nullable|integer|min:1',
            'observation'            => 'nullable|string',
        ];
    }
}
