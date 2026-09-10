<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnneeCatecheseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $totalInscrits = (int) (
            $this->total_inscrits
            ?? $this->inscriptions_annuelles_count
            ?? ($this->relationLoaded('inscriptionsAnnuelles')
                ? $this->inscriptionsAnnuelles->where('statut_inscription', '!=', 'annulee')->count()
                : $this->inscriptionsAnnuelles()->where('statut_inscription', '!=', 'annulee')->count())
        );

        return [
            'id' => $this->uuid,
            'libelle' => $this->libelle,
            'date_debut' => $this->date_debut?->toDateString(),
            'date_fin' => $this->date_fin?->toDateString(),
            'statut' => $this->statut,
            'total_inscrits' => $totalInscrits,
            'inscrits_count' => $totalInscrits,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}