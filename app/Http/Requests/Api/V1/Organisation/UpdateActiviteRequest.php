<?php

namespace App\Http\Requests\Api\V1\Organisation;

use App\Models\Membre;
use Illuminate\Foundation\Http\FormRequest;

class UpdateActiviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('responsable_id') && !empty($this->responsable_id) && !is_numeric($this->responsable_id)) {
            $id = Membre::where('uuid', $this->responsable_id)->value('id');
            if ($id) {
                $this->merge(['responsable_id' => $id]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'code'           => 'nullable|string|max:50',
            'titre'          => 'sometimes|required|string|max:255',
            'description'    => 'nullable|string',
            'type_activite'  => 'nullable|string|max:100',
            'date_debut'     => 'sometimes|required|date',
            'date_fin'       => 'nullable|date|after_or_equal:date_debut',
            'lieu'           => 'nullable|string|max:255',
            'responsable_id' => 'nullable|exists:membres,id',
            'statut'         => 'sometimes|required|string|in:brouillon,planifiee,en_cours,terminee,annulee',
            'taux_execution' => 'nullable|numeric|min:0|max:100',
            'observation'    => 'nullable|string',
        ];
    }
}
