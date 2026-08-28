<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

use DateTimeImmutable;

final readonly class Provenance
{
    /**
     * @param  list<string>  $transformationLineage
     */
    public function __construct(
        public string $id,
        public SourceLocator $source,
        public Producer $producer,
        public ?DateTimeImmutable $occurredAt,
        public DateTimeImmutable $observedAt,
        public DateTimeImmutable $recordedAt,
        public array $transformationLineage,
    ) {}
}
