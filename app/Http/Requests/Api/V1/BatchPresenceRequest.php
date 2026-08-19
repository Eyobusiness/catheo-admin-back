<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class BatchPresenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'presences' => ['required', 'array', 'min:1'],
            'presences.*.catechumene_id' => ['required', 'string', 'exists:catechumenes,uuid'],
            'presences.*.statut_presence' => ['required', 'string', 'in:present,absent,retard,excuse'],
            'presences.*.remarque' => ['nullable', 'string', 'max:255'],
        ];
    }
}
