<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

use DateTimeImmutable;

final readonly class MetadataAssertion
{
    public function __construct(
        public string $id,
        public string $namespace,
        public string $schemaVersion,
        public mixed $attributes,
        public ?string $provenanceId,
        public DateTimeImmutable $recordedAt,
    ) {}
}
