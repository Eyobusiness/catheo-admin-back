<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->uuid,
            'uuid'             => $this->uuid,
            'name'             => $this->name,
            'nom'              => $this->nom,
            'prenoms'          => $this->prenoms,
            'lastName'         => $this->nom ?? $this->name,
            'firstName'        => $this->prenoms ?? '',
            'email'            => $this->email,
            'username'         => $this->username,
            'telephone'        => $this->telephone,
            'phone'            => $this->telephone,
            'user_type'        => $this->user_type ?? 'admin',
            'statut'           => $this->statut,
            'status'           => $this->statut,
            'dernier_login_at' => $this->dernier_login_at?->toIso8601String(),
            'paroisse'         => new ParoisseResource($this->whenLoaded('paroisse')),
            'profil'           => new ProfilResource($this->whenLoaded('profil')),
            'profile'          => new ProfilResource($this->whenLoaded('profil')),
            'animateur'        => new AnimateurResource($this->whenLoaded('animateur')),
            'catechumene'      => new CatechumeneResource($this->whenLoaded('catechumene')),
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
