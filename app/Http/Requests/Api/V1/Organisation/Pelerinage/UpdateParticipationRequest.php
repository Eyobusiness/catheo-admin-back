<?php

namespace App\Http\Requests\Api\V1\Organisation\Pelerinage;

use App\Models\InscriptionPelerinage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateParticipationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'statut_participation' => ['required', 'string', Rule::in(InscriptionPelerinage::PARTICIPATIONS)],
            'badge_imprime'        => 'nullable|boolean',
            'kit_remis'            => 'nullable|boolean',
            'observation'          => 'nullable|string',
        ];
    }
}
