<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $paroisse = $this->relationLoaded('paroisse') ? $this->paroisse : ($this->relationLoaded('catechese') ? $this->catechese : null);
        $profil = $this->relationLoaded('profil') ? $this->profil : null;
        $animateur = $this->relationLoaded('animateur') ? $this->animateur : null;
        $catechumene = $this->relationLoaded('catechumene') ? $this->catechumene : null;

        return [
            'id'                        => $this->uuid,
            'uuid'                      => $this->uuid,
            'paroisse_configuration_id' => $this->paroisse_configuration_id,
            'paroisse_id'               => $this->paroisse_configuration_id,
            'name'                      => $this->name,
            'nom'                       => $this->nom,
            'prenoms'                   => $this->prenoms,
            'lastName'                  => $this->nom ?? $this->name,
            'firstName'                 => $this->prenoms ?? '',
            'email'                     => $this->email,
            'username'                  => $this->username,
            'telephone'                 => $this->telephone,
            'phone'                     => $this->telephone,
            'user_type'                 => $this->user_type ?? 'admin',
            'statut'                    => $this->statut,
            'status'                    => $this->statut,
            'dernier_login_at'          => $this->dernier_login_at?->toIso8601String(),
            'catechese'                 => $paroisse ? new CatecheseConfigurationResource($paroisse) : null,
            'paroisse'                  => $paroisse ? new CatecheseConfigurationResource($paroisse) : null,
            'profil'                    => $profil ? new ProfilResource($profil) : null,
            'profile'                   => $profil ? new ProfilResource($profil) : null,
            'animateur'                 => $animateur ? new AnimateurResource($animateur) : null,
            'catechumene'               => $catechumene ? new CatechumeneResource($catechumene) : null,
            'created_at'                => $this->created_at?->toIso8601String(),
        ];
    }
}
