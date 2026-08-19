<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApparenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'couleur_principale' => $this->couleur_principale ?? '#4F46E5',
            'couleur_secondaire' => $this->couleur_secondaire ?? '#D97706',
            'police_caracteres' => $this->police_caracteres ?? 'Inter',
            'logo_url' => $this->logo_url,
            'entete_document' => $this->entete_document,
            'pied_page_document' => $this->pied_page_document,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
