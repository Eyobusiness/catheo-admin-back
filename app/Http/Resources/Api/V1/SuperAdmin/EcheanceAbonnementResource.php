<?php

namespace App\Http\Resources\Api\V1\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EcheanceAbonnementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->uuid,
            'id_interne'     => $this->id,
            'abonnement_id'  => $this->abonnement?->uuid ?? $this->abonnement_id,
            'reference'      => $this->reference,
            'periode_debut'  => $this->periode_debut?->toDateString(),
            'periode_fin'    => $this->periode_fin?->toDateString(),
            'date_echeance'  => $this->date_echeance?->toDateString(),
            'montant'        => (float) $this->montant,
            'montant_paye'   => (float) $this->montant_paye,
            'solde_restant'  => (float) $this->solde_restant,
            'devise'         => $this->devise,
            'statut'         => $this->statut,
            'observation'    => $this->observation,
            'facture'        => new FactureResource($this->whenLoaded('facture')),
            'paiements'      => PaiementAbonnementResource::collection($this->whenLoaded('paiements')),
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
