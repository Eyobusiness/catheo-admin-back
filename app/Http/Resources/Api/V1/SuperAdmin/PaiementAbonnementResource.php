<?php

namespace App\Http\Resources\Api\V1\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaiementAbonnementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->uuid,
            'id_interne'             => $this->id,
            'echeance_id'            => $this->echeance?->uuid ?? $this->echeance_abonnement_id,
            'echeance_reference'     => $this->echeance?->reference,
            'reference'              => $this->reference,
            'montant'                => (float) $this->montant,
            'devise'                 => $this->devise,
            'mode_paiement'          => $this->mode_paiement,
            'date_paiement'          => $this->date_paiement?->toDateString(),
            'statut'                 => $this->statut,
            'reference_transaction'  => $this->reference_transaction,
            'observation'            => $this->observation,
            'created_by'             => $this->created_by,
            'caissier_nom'           => $this->caissier?->nom,
            'created_at'             => $this->created_at?->toIso8601String(),
            'updated_at'             => $this->updated_at?->toIso8601String(),
        ];
    }
}
