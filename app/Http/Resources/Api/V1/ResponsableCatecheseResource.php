<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResponsableCatecheseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->uuid,
            'nom_prenoms' => $this->nom_prenoms,
            'fonction'    => $this->fonction,
            'telephone'   => $this->telephone,
            'statut'      => $this->statut ?? 'actif',
            'created_at'  => $this->created_at?->toIso8601String(),
            'updated_at'  => $this->updated_at?->toIso8601String(),
            'created_by'  => $this->created_by,
            'updated_by'  => $this->updated_by,
        ];
    }
}
