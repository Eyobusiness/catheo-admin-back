<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->uuid,
            'uuid'       => $this->uuid,
            'order'      => $this->ordre,
            'ordre'      => $this->ordre,
            'libelle'    => $this->libelle,
            'icon'       => $this->icon,
            'path'       => $this->path,
            'code'       => $this->code ?? '',
            'permission' => $this->permission,
            'reference'  => $this->reference,
            'is_active'  => $this->is_active,
            'parent_id'  => $this->parent?->uuid,
            'sousMenus'  => MenuResource::collection($this->whenLoaded('sousMenus')),
        ];
    }
}
