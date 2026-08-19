<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DecisionFinAnneeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'moyenne_annuelle' => $this->moyenne_annuelle !== null ? (float) $this->moyenne_annuelle : null,
            'decision' => $this->decision,
            'mention' => $this->mention,
            'sacrement_recu' => $this->sacrement_recu,
            'date_decision' => $this->date_decision?->toDateString(),
            'observations' => $this->observations,
            'inscription_annuelle' => new InscriptionAnnuelleResource($this->whenLoaded('inscriptionAnnuelle')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
