<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

use DateTimeImmutable;

final readonly class TextAssertion
{
    public function __construct(
        public string $id,
        public string $observationId,
        public ?string $provenanceId,
        public string $kind,
        public string $contentType,
        public string $text,
        public string $textHash,
        public ?string $language,
        public DateTimeImmutable $recordedAt,
    ) {}
}
