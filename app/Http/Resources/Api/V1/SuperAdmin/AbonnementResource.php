<?php

namespace App\Http\Resources\Api\V1\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AbonnementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                         => $this->uuid,
            'id_interne'                 => $this->id,
            'reference'                  => $this->reference,
            'paroisse_id'                => $this->paroisse?->uuid ?? $this->paroisse_configuration_id,
            'paroisse_nom'               => $this->paroisse?->nom_paroisse,
            'paroisse_code'              => $this->paroisse?->code_paroisse,
            'produit_code'               => $this->formule?->produit?->code,
            'produit_nom'                => $this->formule?->produit?->nom,
            'formule_id'                 => $this->formule?->uuid ?? $this->formule_id,
            'formule_nom'                => $this->formule?->nom,
            'formule_code'               => $this->formule?->code,
            'date_debut'                 => $this->date_debut?->toDateString(),
            'date_fin'                   => $this->date_fin?->toDateString(),
            'statut'                     => $this->statut,
            'montant'                    => (float) $this->montant,
            'devise'                     => $this->devise,
            'renouvellement_automatique' => (bool) $this->renouvellement_automatique,
            'date_resiliation'           => $this->date_resiliation?->toDateString(),
            'motif_resiliation'          => $this->motif_resiliation,
            'observation'                => $this->observation,
            'echeances'                  => EcheanceAbonnementResource::collection($this->whenLoaded('echeances')),
            'created_at'                 => $this->created_at?->toIso8601String(),
            'updated_at'                 => $this->updated_at?->toIso8601String(),
        ];
    }
}
