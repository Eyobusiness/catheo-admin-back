<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CalculerBulletinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'classe_id' => ['required', 'string', 'exists:classes,uuid'],
            'module_trimestriel_id' => ['required', 'string', 'exists:modules_trimestriels,uuid'],
        ];
    }
}
