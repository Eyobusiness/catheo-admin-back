<?php

namespace App\Http\Resources\Api\V1\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SuperAdminParoisseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Récupérer les abonnements actifs et les codes produits souscrits
        $abonnementsActifs = $this->abonnements?->where('statut', 'actif') ?? collect();
        $produitsSouscrits = $abonnementsActifs->map(function ($abo) {
            return [
                'produit_code' => $abo->formule?->produit?->code,
                'produit_nom'  => $abo->formule?->produit?->nom,
                'formule_nom'  => $abo->formule?->nom,
                'date_fin'     => $abo->date_fin?->toDateString(),
            ];
        })->values();

        return [
            'id'                 => $this->uuid,
            'id_interne'         => $this->id,
            'nom_paroisse'       => $this->nom_paroisse,
            'code_paroisse'      => $this->code_paroisse,
            'diocese'            => $this->diocese,
            'doyenne'            => $this->doyenne,
            'ville'              => $this->ville,
            'commune'            => $this->commune,
            'telephone'          => $this->telephone,
            'email'              => $this->email,
            'statut'             => $this->statut,
            'total_abonnements'  => $this->abonnements?->count() ?? 0,
            'produits_souscrits' => $produitsSouscrits,
            'abonnements'        => AbonnementResource::collection($this->whenLoaded('abonnements')),
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}
