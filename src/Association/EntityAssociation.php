<?php

declare(strict_types=1);

namespace Sifrious\Funes\Association;

use DateTimeImmutable;
use Sifrious\ReferenceContract\CrossPackageReference;

final readonly class EntityAssociation
{
    /**
     * @param  list<string>  $provenanceIds
     */
    public function __construct(
        public string $id,
        public string $observationId,
        public EntityAssociationRole $role,
        public CrossPackageReference $entity,
        public array $provenanceIds,
        public DateTimeImmutable $recordedAt,
    ) {}
}
