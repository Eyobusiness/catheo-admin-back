<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResponsableParoisseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'titre' => $this->titre,
            'nom_prenoms' => $this->nom_prenoms,
            'fonction' => $this->fonction,
            'telephone' => $this->telephone,
            'email' => $this->email,
            'signature_path' => $this->signature_path,
            'signature_url' => $this->signature_path ? asset('storage/' . $this->signature_path) : null,
            'ordre_affichage' => $this->ordre_affichage,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
