<?php

namespace App\DTO\Evaluation;

class MoyenneItemDTO
{
    public function __construct(
        public readonly string $catechumeneId,
        public readonly ?string $matricule,
        public readonly string $nomPrenoms,
        public readonly int $nombreEvaluations,
        public readonly int $nombreNotes,
        public readonly ?float $moyenne,
        public readonly string $appreciation,
        public readonly array $detailsNotes = [],
    ) {}

    public function toArray(): array
    {
        return [
            'catechumene_id'     => $this->catechumeneId,
            'catechumeneId'      => $this->catechumeneId,
            'matricule'          => $this->matricule,
            'code_catechumene'   => $this->matricule,
            'nom_prenoms'        => $this->nomPrenoms,
            'nomPrenoms'         => $this->nomPrenoms,
            'nombre_evaluations' => $this->nombreEvaluations,
            'nombreEvaluations'  => $this->nombreEvaluations,
            'nombre_notes'       => $this->nombreNotes,
            'nombreNotes'        => $this->nombreNotes,
            'moyenne'            => $this->moyenne,
            'appreciation'       => $this->appreciation,
            'details_notes'      => $this->detailsNotes,
        ];
    }
}
