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
            'total_organisations'=> $this->organisations?->count() ?? 0,
            'produits_souscrits' => $produitsSouscrits,
            'organisations'      => $this->organisations ? $this->organisations->map(function ($org) {
                return [
                    'id'                    => $org->uuid,
                    'uuid'                  => $org->uuid,
                    'id_interne'            => $org->id,
                    'type_organisation'     => $org->type_organisation,
                    'produit_code'          => $org->produit?->code ?? $org->type_organisation,
                    'produit_nom'           => $org->produit?->nom ?? $org->type_organisation,
                    'nom'                   => $org->nom,
                    'code'                  => $org->code,
                    'description'           => $org->description,
                    'logo_path'             => $org->logo_path,
                    'logo_url'              => $org->logo_url,
                    'statut'                => $org->statut,
                    'responsable_nom'       => $org->responsable_nom,
                    'responsable_telephone' => $org->responsable_telephone,
                    'responsable_email'     => $org->responsable_email,
                    'created_at'            => $org->created_at?->toIso8601String(),
                ];
            })->values() : [],
            'abonnements'        => AbonnementResource::collection($this->whenLoaded('abonnements')),
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}
