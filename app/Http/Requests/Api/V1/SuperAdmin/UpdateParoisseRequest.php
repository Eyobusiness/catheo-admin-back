<?php

namespace App\Http\Requests\Api\V1\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateParoisseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $paroisseId = null;
        $id = $this->route('id');
        if ($id) {
            $paroisse = \App\Models\CatecheseConfiguration::where('uuid', $id)->orWhere('id', $id)->first();
            $paroisseId = $paroisse?->id;
        }

        return [
            'nom_paroisse'     => ['sometimes', 'required', 'string', 'max:255'],
            'code_paroisse'    => ['sometimes', 'required', 'string', 'max:20', "unique:paroisse_configurations,code_paroisse,{$paroisseId}", 'regex:/^[A-Za-z0-9_-]+$/'],
            'diocese'          => ['sometimes', 'required', 'string', 'max:150'],
            'doyenne'          => ['nullable', 'string', 'max:150'],
            'ville'            => ['nullable', 'string', 'max:100'],
            'commune'          => ['nullable', 'string', 'max:100'],
            'telephone'        => ['nullable', 'string', 'max:30'],
            'email'            => ['nullable', 'email', 'max:150'],
            'adresse'          => ['nullable', 'string', 'max:500'],
            'cure_nom'         => ['nullable', 'string', 'max:255'],
            'coordination_nom' => ['nullable', 'string', 'max:255'],
            'logo_paroisse'    => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'logo_catechese'   => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'statut'           => ['nullable', 'string', 'in:cree,actif,suspendu,inactif'],
        ];
    }
}