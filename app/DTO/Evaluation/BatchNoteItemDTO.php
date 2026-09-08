<?php

namespace App\DTO\Evaluation;

class BatchNoteItemDTO
{
    public function __construct(
        public readonly string|int $catechumeneIdentifier,
        public readonly ?float $noteObtenue,
        public readonly ?string $appreciation = null,
    ) {}

    public static function fromArray(array $item): self
    {
        $id = $item['catechumene_id'] ?? $item['catechumeneId'] ?? $item['id'] ?? null;
        $rawNote = $item['note_obtenue'] ?? $item['note'] ?? null;

        $note = ($rawNote !== null && $rawNote !== '') ? (float) $rawNote : null;

        return new self(
            catechumeneIdentifier: $id,
            noteObtenue: $note,
            appreciation: $item['appreciation'] ?? null,
        );
    }
}
