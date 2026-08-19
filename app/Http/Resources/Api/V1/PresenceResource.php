<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PresenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'statut_presence' => $this->statut_presence,
            'motif_absence' => $this->motif_absence,
            'catechumene' => new CatechumeneResource($this->whenLoaded('catechumene')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
