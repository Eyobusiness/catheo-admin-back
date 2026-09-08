<?php

namespace App\DTO\Evaluation;

class CreateEvaluationDTO
{
    public function __construct(
        public readonly int $paroisseConfigurationId,
        public readonly int $anneeCatecheseId,
        public readonly ?int $classeId,
        public readonly ?int $moduleTrimestrielId,
        public readonly string $titre,
        public readonly ?string $description,
        public readonly string $typeEval,
        public readonly float $coefficient,
        public readonly float $noteMax,
        public readonly string $dateEvaluation,
        public readonly string $statut = 'actif',
    ) {}

    public static function fromArray(array $data, int $paroisseId, int $anneeId, ?int $classeId, ?int $moduleId): self
    {
        return new self(
            paroisseConfigurationId: $paroisseId,
            anneeCatecheseId: $anneeId,
            classeId: $classeId,
            moduleTrimestrielId: $moduleId,
            titre: trim($data['titre'] ?? $data['nom'] ?? ''),
            description: $data['description'] ?? $data['observation'] ?? null,
            typeEval: strtolower($data['type_eval'] ?? $data['type'] ?? 'interrogation'),
            coefficient: (float) ($data['coefficient'] ?? 1.0),
            noteMax: (float) ($data['note_max'] ?? $data['bareme'] ?? 20.0),
            dateEvaluation: $data['date_evaluation'] ?? $data['date'] ?? now()->toDateString(),
            statut: strtolower($data['statut'] ?? $data['status'] ?? 'actif') === 'inactif' ? 'inactif' : 'actif',
        );
    }

    public function toArray(): array
    {
        return [
            'paroisse_configuration_id' => $this->paroisseConfigurationId,
            'annee_catechese_id'        => $this->anneeCatecheseId,
            'classe_id'                 => $this->classeId,
            'module_trimestriel_id'     => $this->moduleTrimestrielId,
            'titre'                     => $this->titre,
            'description'               => $this->description,
            'type_eval'                 => $this->typeEval,
            'coefficient'               => $this->coefficient,
            'note_max'                  => $this->noteMax,
            'date_evaluation'           => $this->dateEvaluation,
            'statut'                    => $this->statut,
        ];
    }
}
