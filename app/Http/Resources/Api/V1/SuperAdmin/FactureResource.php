<?php

namespace App\Http\Resources\Api\V1\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FactureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->uuid,
            'id_interne'         => $this->id,
            'echeance_id'        => $this->echeance?->uuid ?? $this->echeance_abonnement_id,
            'echeance_reference' => $this->echeance?->reference,
            'paroisse_nom'       => $this->echeance?->abonnement?->paroisse?->nom_paroisse,
            'produit_code'       => $this->echeance?->abonnement?->formule?->produit?->code,
            'produit_nom'        => $this->echeance?->abonnement?->formule?->produit?->nom,
            'reference'          => $this->reference,
            'date_facture'       => $this->date_facture?->toDateString(),
            'date_echeance'      => $this->date_echeance?->toDateString(),
            'montant_ht'         => (float) $this->montant_ht,
            'taux_tva'           => (float) $this->taux_tva,
            'montant_tva'        => (float) $this->montant_tva,
            'montant_total'      => (float) $this->montant_total,
            'devise'             => $this->devise,
            'statut'             => $this->statut,
            'description'        => $this->description,
            'observation'        => $this->observation,
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}
