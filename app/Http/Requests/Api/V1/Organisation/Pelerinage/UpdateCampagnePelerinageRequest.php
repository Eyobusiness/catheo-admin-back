<?php

namespace App\Http\Requests\Api\V1\Organisation\Pelerinage;

use App\Models\CampagnePelerinage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampagnePelerinageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom'                    => 'sometimes|required|string|max:255',
            'description'            => 'nullable|string',
            'activite_id'            => 'nullable|integer',
            'lieu_depart'            => 'sometimes|required|string|max:255',
            'destination'            => 'sometimes|required|string|max:255',
            'date_depart'            => 'sometimes|required|date',
            'heure_depart'           => 'nullable|date_format:H:i',
            'date_fin'               => 'sometimes|required|date|after_or_equal:date_depart',
            'heure_fin'              => 'nullable|date_format:H:i',
            'date_debut_inscription' => 'nullable|date',
            'date_fin_inscription'   => 'nullable|date|after_or_equal:date_debut_inscription',
            'capacite'               => 'nullable|integer|min:1',
            'statut'                 => ['nullable', 'string', Rule::in(CampagnePelerinage::STATUTS)],
            'observation'            => 'nullable|string',
        ];
    }
}
