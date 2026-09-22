<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NiveauResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $section = $this->relationLoaded('section') ? $this->section : null;
        if (!$section && $this->section_id) {
            $section = \App\Models\Section::find($this->section_id);
        }
        $sectionUuid = $section?->uuid;

        return [
            'id' => $this->uuid,
            'nom' => $this->nom,
            'description' => $this->description,
            'statut' => ucfirst($this->statut ?? 'actif'),
            'statut_code' => $this->statut ?? 'actif',
            'ordre_affichage' => $this->ordre_affichage,
            'section_id' => $sectionUuid ?? (string) $this->section_id,
            'section_uuid' => $sectionUuid,
            'section' => $section ? [
                'id' => $section->uuid,
                'nom' => $section->nom,
                'code' => $section->code,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}