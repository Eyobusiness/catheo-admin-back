<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModeleDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->uuid ?? (string) $this->id,
            'uuid'                  => $this->uuid,
            'titre'                 => $this->titre,
            'code'                  => $this->code,
            'type_document'         => $this->type_document,
            'description'           => $this->description,
            'contenu'               => $this->contenu,
            'variables_disponibles' => $this->variables_disponibles ?? [],
            'signature_nom'         => $this->signature_nom,
            'signature_titre'       => $this->signature_titre,
            'statut'                => $this->statut,
            'is_system'             => (bool) $this->is_system,
            'created_at'            => $this->created_at?->toISOString(),
            'updated_at'            => $this->updated_at?->toISOString(),
        ];
    }
}
