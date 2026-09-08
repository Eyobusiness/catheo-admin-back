<?php

namespace App\DTO\Evaluation;

class SaveNotesBatchDTO
{
    /**
     * @param BatchNoteItemDTO[] $items
     */
    public function __construct(
        public readonly array $items,
    ) {}

    public static function fromArray(array $data): self
    {
        $rawNotes = $data['notes'] ?? [];
        $items = [];

        foreach ($rawNotes as $item) {
            if (is_array($item)) {
                $items[] = BatchNoteItemDTO::fromArray($item);
            }
        }

        return new self($items);
    }
}
