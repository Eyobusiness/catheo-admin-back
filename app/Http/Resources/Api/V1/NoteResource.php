<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Evaluation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $noteObtenue = (float) $this->note_obtenue;
        $noteMax = $this->evaluation ? (float) $this->evaluation->note_max : 20.0;
        $appreciation = $this->appreciation ?: Evaluation::calculateAppreciation($noteObtenue, $noteMax);

        return [
            'id'               => $this->uuid,
            'catechumene_id'   => $this->catechumene?->uuid,
            'catechumeneId'    => $this->catechumene?->uuid,
            'evaluation_id'    => $this->evaluation?->uuid,
            'evaluationId'     => $this->evaluation?->uuid,
            'matricule'        => $this->catechumene?->matricule,
            'code_catechumene' => $this->catechumene?->matricule,
            'nom_prenoms'      => $this->catechumene ? trim("{$this->catechumene->nom} {$this->catechumene->prenoms}") : null,
            'nomPrenoms'       => $this->catechumene ? trim("{$this->catechumene->nom} {$this->catechumene->prenoms}") : null,
            'note_obtenue'     => $noteObtenue,
            'note'             => $noteObtenue,
            'appreciation'     => $appreciation,
            'catechumene'      => new CatechumeneResource($this->whenLoaded('catechumene')),
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
