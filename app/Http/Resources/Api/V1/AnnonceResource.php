<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnonceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isRead = false;

        if ($user) {
            $isRead = $this->resource->estLuePar($user);
        }

        return [
            'id'               => $this->uuid,
            'titre'            => $this->titre,
            'contenu'          => $this->contenu,
            'cible'            => $this->cible ?? strtolower($this->cible_type ?? 'tous'),
            'cible_type'       => $this->cible_type ?? 'Tous',
            'cible_id'         => $this->cible_id,
            'cible_ids'        => $this->cible_ids,
            'cible_nom'        => $this->cible_nom,
            'canal'            => $this->canal ?? 'in_app',
            'date_publication' => $this->date_publication?->toDateString() ?? $this->date_diffusion?->toDateString(),
            'date_diffusion'   => $this->date_diffusion?->toDateString() ?? $this->date_publication?->toDateString(),
            'heure_diffusion'  => $this->heure_diffusion,
            'date_expiration'  => $this->date_expiration?->toDateString(),
            'priorite'         => $this->priorite ?? 'normale',
            'statut'           => $this->statut ?? 'publiee',
            'est_lu'           => $isRead,
            'is_read'          => $isRead,
            'annee_catechese'  => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'section'          => new SectionResource($this->whenLoaded('section')),
            'niveau'           => new NiveauResource($this->whenLoaded('niveau')),
            'classe'           => new ClasseResource($this->whenLoaded('classe')),
            'ceb'              => new CebResource($this->whenLoaded('ceb')),
            'mouvement'        => new MouvementResource($this->whenLoaded('mouvement')),
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
