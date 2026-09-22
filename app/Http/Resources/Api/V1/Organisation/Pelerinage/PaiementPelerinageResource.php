<?php

namespace App\Http\Resources\Api\V1\Organisation\Pelerinage;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaiementPelerinageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            'uuid'                      => $this->uuid,
            'inscription_pelerinage_id' => $this->inscription_pelerinage_id,
            'reference'                 => $this->reference,
            'montant'                   => (float) $this->montant,
            'devise'                    => $this->devise,
            'mode_paiement'             => $this->mode_paiement,
            'date_paiement'             => $this->date_paiement?->toIso8601String(),
            'statut'                    => $this->statut,
            'reference_transaction'     => $this->reference_transaction,
            'observation'               => $this->observation,
            'caissier'                  => $this->whenLoaded('caissier', function () {
                return [
                    'id'    => $this->caissier->id,
                    'name'  => $this->caissier->name,
                    'email' => $this->caissier->email,
                ];
            }),
            'inscription'               => new InscriptionPelerinageResource($this->whenLoaded('inscription')),
            'created_at'                => $this->created_at?->toIso8601String(),
            'updated_at'                => $this->updated_at?->toIso8601String(),
        ];
    }
}
