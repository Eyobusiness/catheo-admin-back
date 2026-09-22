<?php

namespace App\Http\Requests\Api\V1\Organisation\Pelerinage;

use App\Models\InscriptionPelerinage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BatchParticipationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inscriptions'                         => 'required|array|min:1',
            'inscriptions.*.id'                    => 'required|integer',
            'inscriptions.*.statut_participation'  => ['required', 'string', Rule::in(InscriptionPelerinage::PARTICIPATIONS)],
            'inscriptions.*.kit_remis'             => 'nullable|boolean',
            'inscriptions.*.badge_imprime'         => 'nullable|boolean',
        ];
    }
}
