<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaiementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'numero_recu' => $this->numero_recu,
            'montant_total' => (float) $this->montant_total,
            'remise' => (float) ($this->remise ?? 0),
            'montant_net' => max(0, (float) $this->montant_total - (float) ($this->remise ?? 0)),
            'mode_paiement' => $this->mode_paiement,
            'reference_transaction' => $this->reference_transaction,
            'date_paiement' => $this->date_paiement?->toDateString(),
            'statut' => $this->statut,
            'notes' => $this->notes,
            'catechumene' => new CatechumeneResource($this->whenLoaded('catechumene')),
            'annee_catechese' => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'inscription_annuelle' => new InscriptionAnnuelleResource($this->whenLoaded('inscriptionAnnuelle')),
            'lignes' => LignePaiementResource::collection($this->whenLoaded('lignes')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
