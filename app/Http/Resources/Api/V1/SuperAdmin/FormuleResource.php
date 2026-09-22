<?php

namespace App\Http\Resources\Api\V1\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->uuid,
            'id_interne'        => $this->id,
            'produit_id'        => $this->produit?->uuid ?? $this->produit_id,
            'produit_code'      => $this->produit?->code,
            'produit_nom'       => $this->produit?->nom,
            'code'              => $this->code,
            'nom'               => $this->nom,
            'description'       => $this->description,
            'periodicite'       => $this->periodicite,
            'montant'           => (float) $this->montant,
            'devise'            => $this->devise,
            'est_gratuite'      => (bool) $this->est_gratuite,
            'statut'            => $this->statut,
            'ordre'             => (int) $this->ordre,
            'abonnements_count' => $this->whenCounted('abonnements'),
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
        ];
    }
}
