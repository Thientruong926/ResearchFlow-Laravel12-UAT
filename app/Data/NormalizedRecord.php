<?php

namespace App\Data;

use DateTimeImmutable;

/** Sprint 2 output contract; raw reader rows are NOT normalized records yet. */
final readonly class NormalizedRecord
{
    public function __construct(
        public string $sourceId,
        public int $sourceRowNumber,
        public ?DateTimeImmutable $submittedAt,
        public ?string $schoolCode,
        public ?string $groupCode,
        public array $answers,
        public array $warnings = [],
    ) {
        if ($sourceRowNumber < 1) {
            throw new \InvalidArgumentException('Source row number must be positive.');
        }
    }
}
