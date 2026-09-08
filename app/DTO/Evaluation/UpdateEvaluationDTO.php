<?php

namespace App\DTO\Evaluation;

class UpdateEvaluationDTO
{
    public function __construct(
        public readonly ?string $titre = null,
        public readonly ?string $description = null,
        public readonly ?string $typeEval = null,
        public readonly ?float $coefficient = null,
        public readonly ?float $noteMax = null,
        public readonly ?string $dateEvaluation = null,
        public readonly ?string $statut = null,
        public readonly ?int $anneeCatecheseId = null,
        public readonly ?int $classeId = null,
        public readonly ?int $moduleTrimestrielId = null,
    ) {}

    public static function fromArray(array $data, ?int $anneeId = null, ?int $classeId = null, ?int $moduleId = null): self
    {
        return new self(
            titre: isset($data['titre']) || isset($data['nom']) ? trim($data['titre'] ?? $data['nom']) : null,
            description: array_key_exists('description', $data) || array_key_exists('observation', $data) ? ($data['description'] ?? $data['observation']) : null,
            typeEval: isset($data['type_eval']) || isset($data['type']) ? strtolower($data['type_eval'] ?? $data['type']) : null,
            coefficient: isset($data['coefficient']) ? (float) $data['coefficient'] : null,
            noteMax: isset($data['note_max']) || isset($data['bareme']) ? (float) ($data['note_max'] ?? $data['bareme']) : null,
            dateEvaluation: $data['date_evaluation'] ?? $data['date'] ?? null,
            statut: isset($data['statut']) || isset($data['status']) ? (strtolower($data['statut'] ?? $data['status']) === 'inactif' ? 'inactif' : 'actif') : null,
            anneeCatecheseId: $anneeId,
            classeId: $classeId,
            moduleTrimestrielId: $moduleId,
        );
    }

    public function toFilteredArray(): array
    {
        $attributes = [
            'titre'                 => $this->titre,
            'description'           => $this->description,
            'type_eval'             => $this->typeEval,
            'coefficient'           => $this->coefficient,
            'note_max'              => $this->noteMax,
            'date_evaluation'       => $this->dateEvaluation,
            'statut'                => $this->statut,
            'annee_catechese_id'    => $this->anneeCatecheseId,
            'classe_id'             => $this->classeId,
            'module_trimestriel_id' => $this->moduleTrimestrielId,
        ];

        return array_filter($attributes, fn($val) => $val !== null);
    }
}
