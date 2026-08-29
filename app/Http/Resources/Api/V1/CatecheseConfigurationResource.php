<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatecheseConfigurationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $logoParoissePath = $this->logo_paroisse ?: $this->logo_path;
        $logoCatechesePath = $this->logo_catechese;

        return [
            'id' => $this->uuid,
            'nom_paroisse' => $this->nom_paroisse,
            'nom' => $this->nom_paroisse, // Rétro-compatibilité
            'code_paroisse' => $this->code_paroisse,
            'prefixe_matricule' => $this->prefixe_matricule,
            'prefixe_recu' => $this->prefixe_recu,
            'diocese' => $this->diocese,
            'doyenne' => $this->doyenne,
            'ville' => $this->ville,
            'commune' => $this->commune,
            'telephone' => $this->telephone,
            'email' => $this->email,
            'site_web' => $this->site_web,
            'adresse' => $this->adresse,
            'logo_paroisse' => $this->logo_paroisse,
            'logo_paroisse_url' => $this->logo_paroisse_url,
            'logo_catechese' => $this->logo_catechese,
            'logo_catechese_url' => $this->logo_catechese_url,
            'logo_url' => $this->logo_paroisse_url, // Rétro-compatibilité
            'cure_nom' => $this->cure_nom,
            'coordination_nom' => $this->coordination_nom ?? 'Coordination de la Catéchèse',
            'statut' => $this->statut,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
