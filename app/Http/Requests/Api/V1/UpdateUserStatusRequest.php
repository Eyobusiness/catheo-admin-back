<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['nullable', 'boolean'],
            'statut'    => ['nullable', 'string', 'in:actif,inactif,Actif,Inactif,suspendu,Suspendu'],
        ];
    }
}
