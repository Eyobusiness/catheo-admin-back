<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AffectationAnimateurResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'role_animateur' => $this->role_animateur,
            'animateur' => new AnimateurResource($this->whenLoaded('animateur')),
            'annee_catechese' => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'classe' => new ClasseResource($this->whenLoaded('classe')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
