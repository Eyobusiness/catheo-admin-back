<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'action' => $this->action,
            'entite_type' => $this->entite_type,
            'entite_id' => $this->entite_id,
            'anciennes_valeurs' => $this->anciennes_valeurs,
            'nouvelles_valeurs' => $this->nouvelles_valeurs,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
