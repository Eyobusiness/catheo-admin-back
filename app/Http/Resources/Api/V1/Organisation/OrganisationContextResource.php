<?php

namespace App\Http\Resources\Api\V1\Organisation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganisationContextResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->uuid,
            'id_interne'            => $this->id,
            'type_organisation'     => $this->type_organisation,
            'code'                  => $this->code,
            'nom'                   => $this->nom,
            'description'           => $this->description,
            'statut'                => $this->statut,
            'mode'                  => $this->mode,
            'paroisse_id'           => $this->paroisse_configuration_id,
            'produit_code'          => $this->produit?->code,
            'produit_nom'           => $this->produit?->nom,
            'paroisse'              => $this->paroisse ? [
                'id'            => $this->paroisse->uuid ?? $this->paroisse->id,
                'nom_paroisse'  => $this->paroisse->nom_paroisse,
                'code_paroisse' => $this->paroisse->code_paroisse,
                'diocese'       => $this->paroisse->diocese,
                'ville'         => $this->paroisse->ville,
            ] : null,
            'responsable'           => $this->responsable_nom,
            'responsable_nom'       => $this->responsable_nom,
            'responsable_details'   => [
                'nom'       => $this->responsable_nom,
                'telephone' => $this->responsable_telephone,
                'email'     => $this->responsable_email,
            ],
            'telephone'             => $this->telephone,
            'email'                 => $this->email,
            'adresse'               => $this->adresse,
            'contact'               => [
                'telephone' => $this->telephone,
                'email'     => $this->email,
                'adresse'   => $this->adresse,
            ],
            'logo_url'              => $this->logo_url,
            'stats'                 => [
                'total_membres'   => $this->membres()->count(),
                'membres_actifs'  => $this->membres()->where('statut', 'actif')->count(),
                'total_activites' => $this->activites()->count(),
                'total_users'     => $this->users()->count(),
            ],
            'date_activation'       => $this->date_activation?->toDateString(),
            'created_at'            => $this->created_at?->toIso8601String(),
        ];
    }
}