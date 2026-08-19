<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class BatchNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['required', 'array', 'min:1'],
            'notes.*.catechumene_id' => ['required', 'string', 'exists:catechumenes,uuid'],
            'notes.*.note_obtenue' => ['required', 'numeric', 'min:0'],
            'notes.*.appreciation' => ['nullable', 'string', 'max:255'],
        ];
    }
}
