<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BulletinTrimestrielResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'moyenne_trimestrielle' => $this->moyenne_trimestrielle !== null ? (float) $this->moyenne_trimestrielle : null,
            'rang' => $this->rang,
            'assiduite_total_absences' => $this->assiduite_total_absences,
            'appreciation_generale' => $this->appreciation_generale,
            'statut' => $this->statut,
            'inscription_annuelle' => new InscriptionAnnuelleResource($this->whenLoaded('inscriptionAnnuelle')),
            'module_trimestriel' => new ModuleTrimestrielResource($this->whenLoaded('moduleTrimestriel')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
