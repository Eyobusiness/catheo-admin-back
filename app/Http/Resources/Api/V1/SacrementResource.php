<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SacrementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->uuid,
            'uuid'        => $this->uuid,
            'code'        => $this->code,
            'nom'         => $this->nom,
            'libelle'     => $this->libelle ?? $this->nom,
            'description' => $this->description,
            'ordre'       => $this->ordre,
            'statut'      => $this->statut,
        ];
    }
}
