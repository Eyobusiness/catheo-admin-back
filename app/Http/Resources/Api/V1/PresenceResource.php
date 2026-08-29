<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PresenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->uuid,
            'catechumene_id'  => $this->catechumene?->uuid,
            'statut_presence' => $this->statut_presence,
            'est_present'     => in_array($this->statut_presence, ['present', 'retard']),
            'remarque'        => $this->remarque ?? $this->motif_absence,
            'motif_absence'   => $this->motif_absence ?? $this->remarque,
            'catechumene'     => new CatechumeneResource($this->whenLoaded('catechumene')),
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
