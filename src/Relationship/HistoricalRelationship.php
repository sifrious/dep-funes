<?php

declare(strict_types=1);

namespace Sifrious\Funes\Relationship;

use DateTimeImmutable;
use Sifrious\Funes\Reference\CrossPackageReference;

final readonly class HistoricalRelationship
{
    /**
     * @param  list<string>  $provenanceIds
     * @param  list<RelationshipDeclaration>  $declarations
     */
    public function __construct(
        public string $id,
        public string $observationId,
        public HistoricalRelationshipType $type,
        public CrossPackageReference $target,
        public array $provenanceIds,
        public array $declarations,
        public DateTimeImmutable $recordedAt,
    ) {}
}
