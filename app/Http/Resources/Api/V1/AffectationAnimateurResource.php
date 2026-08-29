<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AffectationAnimateurResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->uuid,
            'annee_catechese_id' => $this->anneeCatechese?->uuid,
            'animateur_id'       => $this->animateur?->uuid,
            'classe_id'          => $this->classe?->uuid,
            'role'               => $this->role_animateur,
            'role_animateur'     => $this->role_animateur,
            'date_affectation'   => $this->created_at?->toDateString(),
            'animateur'          => new AnimateurResource($this->whenLoaded('animateur')),
            'annee_catechese'    => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'classe'             => new ClasseResource($this->whenLoaded('classe')),
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}
