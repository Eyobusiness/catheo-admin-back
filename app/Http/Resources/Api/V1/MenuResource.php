<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->uuid,
            'uuid'      => $this->uuid,
            'libelle'   => $this->libelle,
            'icon'      => $this->icon,
            'path'      => $this->path,
            'reference' => $this->reference,
            'ordre'     => $this->ordre,
            'is_active' => $this->is_active,
            'parent_id' => $this->parent?->uuid,
            'sousMenus' => MenuResource::collection($this->whenLoaded('sousMenus')),
        ];
    }
}
