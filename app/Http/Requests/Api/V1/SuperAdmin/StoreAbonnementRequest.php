<?php

namespace App\Http\Requests\Api\V1\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAbonnementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('paroisse_configuration_id') && !is_numeric($this->paroisse_configuration_id)) {
            $id = \App\Models\CatecheseConfiguration::where('uuid', $this->paroisse_configuration_id)->value('id');
            if ($id) {
                $this->merge(['paroisse_configuration_id' => $id]);
            }
        }
        if ($this->has('formule_id') && !is_numeric($this->formule_id)) {
            $id = \App\Models\Formule::where('uuid', $this->formule_id)->value('id');
            if ($id) {
                $this->merge(['formule_id' => $id]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'paroisse_configuration_id' => 'required|exists:paroisse_configurations,id',
            'formule_id'                => 'required|exists:formules,id',
            'date_debut'                => 'nullable|date',
            'date_fin'                  => 'nullable|date|after_or_equal:date_debut',
            'renouvellement_automatique'=> 'nullable|boolean',
            'observation'               => 'nullable|string',
        ];
    }
}
