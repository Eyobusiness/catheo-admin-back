<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:create,update,delete,login,logout,export'],
            'entite_type' => ['required', 'string', 'max:255'],
            'entite_id' => ['nullable', 'integer'],
            'anciennes_valeurs' => ['nullable', 'array'],
            'nouvelles_valeurs' => ['nullable', 'array'],
        ];
    }
}
