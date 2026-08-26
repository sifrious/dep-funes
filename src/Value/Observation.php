<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

use DateTimeImmutable;

final readonly class Observation
{
    public function __construct(
        public string $id,
        public string $sourceReference,
        public string $sourceName,
        public string $resourceReference,
        public DateTimeImmutable $observedAt,
        public DateTimeImmutable $acceptedAt,
        public string $payload,
        public string $payloadHash,
        public string $mediaType,
        public mixed $metadata,
        public mixed $discoveries,
    ) {}
}
