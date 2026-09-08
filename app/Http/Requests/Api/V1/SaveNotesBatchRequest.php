<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SaveNotesBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes'                   => ['required', 'array'],
            'notes.*.catechumene_id'  => ['sometimes', 'required_without:notes.*.catechumeneId'],
            'notes.*.catechumeneId'   => ['sometimes', 'required_without:notes.*.catechumene_id'],
            'notes.*.note_obtenue'    => ['nullable', 'numeric', 'min:0'],
            'notes.*.note'            => ['nullable', 'numeric', 'min:0'],
            'notes.*.appreciation'    => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'notes.required' => 'Le tableau de notes est obligatoire.',
            'notes.array'    => 'Le format des notes est invalide.',
            'notes.*.note_obtenue.min' => 'La note ne peut pas être négative.',
            'notes.*.note.min'         => 'La note ne peut pas être négative.',
        ];
    }
}
