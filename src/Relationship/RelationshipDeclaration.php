<?php

declare(strict_types=1);

namespace Sifrious\Funes\Relationship;

use DateTimeImmutable;

final readonly class RelationshipDeclaration
{
    public function __construct(
        public string $id,
        public string $provenanceId,
        public string $sourceLocator,
        public string $declaredValue,
        public DateTimeImmutable $recordedAt,
    ) {}
}
