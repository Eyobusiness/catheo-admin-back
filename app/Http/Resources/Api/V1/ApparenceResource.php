<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApparenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->uuid,
            'couleur_principale' => $this->couleur_principale ?? '#4F46E5',
            'couleur_secondaire' => $this->couleur_secondaire ?? '#D97706',
            'police_caracteres'  => $this->police_caracteres ?? 'Inter',
            'entete_document'    => $this->entete_document,
            'pied_page_document' => $this->pied_page_document,
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
            'created_by'         => $this->created_by,
            'updated_by'         => $this->updated_by,
        ];
    }
}
