<?php

namespace App\Http\Resources\Api\V1\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProduitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->uuid,
            'id_interne'          => $this->id,
            'code'                => $this->code,
            'nom'                 => $this->nom,
            'description'         => $this->description,
            'icone'               => $this->icone,
            'statut'              => $this->statut,
            'formules_count'      => $this->whenCounted('formules'),
            'organisations_count' => $this->whenCounted('organisations'),
            'created_at'          => $this->created_at?->toIso8601String(),
            'updated_at'          => $this->updated_at?->toIso8601String(),
        ];
    }
}
