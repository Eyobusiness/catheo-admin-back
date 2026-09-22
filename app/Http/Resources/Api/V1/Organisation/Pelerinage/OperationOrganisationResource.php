<?php

namespace App\Http\Resources\Api\V1\Organisation\Pelerinage;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperationOrganisationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            'uuid'                      => $this->uuid,
            'organisation_id'           => $this->organisation_id,
            'campagne_pelerinage_id'    => $this->campagne_pelerinage_id,
            'inscription_pelerinage_id' => $this->inscription_pelerinage_id,
            'paiement_pelerinage_id'    => $this->paiement_pelerinage_id,
            'reference'                 => $this->reference,
            'type_operation'            => $this->type_operation,
            'montant'                   => (float) $this->montant,
            'devise'                    => $this->devise,
            'libelle'                   => $this->libelle,
            'mode_reglement'            => $this->mode_reglement,
            'date_operation'            => $this->date_operation?->toIso8601String(),
            'statut'                    => $this->statut,
            'operateur'                 => $this->whenLoaded('operateur', function () {
                return [
                    'id'   => $this->operateur->id,
                    'name' => $this->operateur->name,
                ];
            }),
            'created_at'                => $this->created_at?->toIso8601String(),
        ];
    }
}
