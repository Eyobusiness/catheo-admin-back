<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TypeActiviteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'nom' => $this->nom,
            'code' => $this->code,
            'couleur_agenda' => $this->couleur_agenda,
            'description' => $this->description,
            'statut' => $this->statut,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
