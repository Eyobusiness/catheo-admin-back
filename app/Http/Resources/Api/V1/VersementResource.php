<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VersementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->uuid ?? (string) $this->id,
            'uuid'              => $this->uuid,
            'reference'         => $this->reference,
            'periode_concernee' => $this->periode_concernee,
            'montant_verse'     => (float) $this->montant_verse,
            'mode_remise'       => $this->mode_remise,
            'effectue_par'      => $this->effectue_par,
            'destinataire'      => $this->destinataire,
            'statut'            => $this->statut,
            'annee_catechese_id'=> $this->anneeCatechese?->uuid,
            'annee_libelle'     => $this->anneeCatechese?->libelle,
            'user'              => $this->whenLoaded('user', function () {
                return [
                    'id'    => $this->user->uuid,
                    'name'  => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'created_at'        => $this->created_at?->toISOString(),
            'updated_at'        => $this->updated_at?->toISOString(),
        ];
    }
}
