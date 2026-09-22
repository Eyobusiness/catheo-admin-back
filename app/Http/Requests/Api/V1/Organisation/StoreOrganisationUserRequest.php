<?php

namespace App\Http\Requests\Api\V1\Organisation;

use App\Models\Profil;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrganisationUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('profil_id') && !empty($this->profil_id) && !is_numeric($this->profil_id)) {
            $id = Profil::where('uuid', $this->profil_id)->orWhere('code', $this->profil_id)->value('id');
            if ($id) {
                $this->merge(['profil_id' => $id]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|max:255|unique:users,email',
            'telephone' => 'nullable|string|max:30',
            'password'  => 'nullable|string|min:8',
            'profil_id' => 'nullable|exists:profils,id',
            'user_type' => 'nullable|string|in:utilisateur,admin',
            'statut'    => 'nullable|string|in:actif,inactif',
        ];
    }
}
