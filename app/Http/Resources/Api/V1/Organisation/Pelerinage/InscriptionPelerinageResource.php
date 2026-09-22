<?php

namespace App\Http\Resources\Api\V1\Organisation\Pelerinage;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InscriptionPelerinageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            'uuid'                      => $this->uuid,
            'campagne_pelerinage_id'    => $this->campagne_pelerinage_id,
            'tarif_pelerinage_id'       => $this->tarif_pelerinage_id,
            'catechumene_id'            => $this->catechumene_id,
            'type_participant'         => $this->type_participant,
            'reference'                 => $this->reference,
            'nom'                       => $this->nom,
            'prenoms'                   => $this->prenoms,
            'nom_complet'               => trim("{$this->nom} {$this->prenoms}"),
            'sexe'                      => $this->sexe,
            'taille'                    => $this->taille,
            'date_naissance'            => $this->date_naissance?->format('Y-m-d'),
            'telephone'                 => $this->telephone,
            'email'                     => $this->email,
            'adresse'                   => $this->adresse,
            'contact_urgence_nom'       => $this->contact_urgence_nom,
            'contact_urgence_telephone' => $this->contact_urgence_telephone,
            'montant'                   => (float) $this->montant,
            'montant_paye'              => (float) $this->montant_paye,
            'reste_a_payer'             => (float) $this->reste_a_payer,
            'statut_inscription'        => $this->statut_inscription,
            'statut_participation'      => $this->statut_participation,
            'date_inscription'          => $this->date_inscription?->toIso8601String(),
            'badge_imprime'             => (bool) $this->badge_imprime,
            'kit_remis'                 => (bool) $this->kit_remis,
            'date_remise_kit'           => $this->date_remise_kit?->toIso8601String(),
            'observation'               => $this->observation,
            'tarif'                     => new TarifPelerinageResource($this->whenLoaded('tarif')),
            'campagne'                  => new CampagnePelerinageResource($this->whenLoaded('campagne')),
            'paiements'                 => PaiementPelerinageResource::collection($this->whenLoaded('paiements')),
            'created_at'                => $this->created_at?->toIso8601String(),
            'updated_at'                => $this->updated_at?->toIso8601String(),
        ];
    }
}
