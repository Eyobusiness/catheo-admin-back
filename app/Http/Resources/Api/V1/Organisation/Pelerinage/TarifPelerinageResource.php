<?php

namespace App\Http\Resources\Api\V1\Organisation\Pelerinage;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TarifPelerinageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'uuid'                   => $this->uuid,
            'campagne_pelerinage_id' => $this->campagne_pelerinage_id,
            'code'                   => $this->code,
            'libelle'                => $this->libelle,
            'description'            => $this->description,
            'montant'                => (float) $this->montant,
            'devise'                 => $this->devise,
            'statut'                 => $this->statut,
            'created_at'             => $this->created_at?->toIso8601String(),
            'updated_at'             => $this->updated_at?->toIso8601String(),
        ];
    }
}
