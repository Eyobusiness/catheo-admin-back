<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemNotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'uuid'                   => $this->uuid,
            'paroisse_configuration_id' => $this->paroisse_configuration_id,
            'type'                   => $this->type,
            'action'                 => $this->action,
            'titre'                  => $this->titre,
            'message'                => $this->message,
            'source_type'            => $this->source_type,
            'source_id'              => $this->source_id,
            'route_url'              => $this->route_url,
            'icon'                   => $this->icon,
            'couleur'                => $this->couleur,
            'donnees_additionnelles' => $this->donnees_additionnelles,
            'is_read'                => (bool) $this->is_read,
            'read_at'                => $this->read_at?->toIso8601String(),
            'created_at'             => $this->created_at?->toIso8601String(),
            'created_at_human'       => $this->created_at ? $this->created_at->diffForHumans() : null,
            'created_by'             => $this->author ? [
                'id'         => $this->author->id,
                'nom'        => $this->author->nom,
                'prenoms'    => $this->author->prenoms,
                'nom_complet'=> "{$this->author->prenoms} {$this->author->nom}",
            ] : null,
        ];
    }
}
