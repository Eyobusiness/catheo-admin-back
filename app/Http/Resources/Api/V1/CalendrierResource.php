<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalendrierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dateFormatted = null;
        if ($this->date) {
            $dateFormatted = is_string($this->date) ? substr($this->date, 0, 10) : $this->date->format('Y-m-d');
        }

        $heureDebut = $this->heure_debut;
        if ($heureDebut && strlen($heureDebut) > 5) {
            $heureDebut = substr($heureDebut, 0, 5);
        }

        $heureFin = $this->heure_fin;
        if ($heureFin && strlen($heureFin) > 5) {
            $heureFin = substr($heureFin, 0, 5);
        }

        return [
            'id'                 => $this->uuid,
            'annee_catechese_id' => $this->anneeCatechese?->uuid,
            'titre'              => $this->titre,
            'type'               => $this->type,
            'date'               => $dateFormatted,
            'heure_debut'        => $heureDebut,
            'heure_fin'          => $heureFin,
            'lieu'               => $this->lieu,
            'cible_type'         => $this->cible_type ?? 'Tous',
            'cible_id'           => $this->cible_id,
            'cible_ids'          => is_array($this->cible_ids) ? $this->cible_ids : ($this->cible_id ? explode(',', $this->cible_id) : []),
            'cible_nom'          => $this->cible_nom,
            'description'        => $this->description,
            'statut'             => $this->statut ?? 'Planifié',
            'annee_catechese'    => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}

