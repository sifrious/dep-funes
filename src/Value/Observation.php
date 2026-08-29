<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

use DateTimeImmutable;
use Sifrious\Funes\Association\EntityAssociation;
use Sifrious\Funes\Association\EntityAssociationRole;
use Sifrious\ReferenceContract\CrossPackageReference;
use Sifrious\Funes\Relationship\HistoricalRelationship;
use Sifrious\Funes\Relationship\HistoricalRelationshipType;

final readonly class Observation
{
    /**
     * @param  list<MetadataAssertion>  $metadata
     * @param  list<TextAssertion>  $texts
     * @param  list<EntityAssociation>  $associations
     * @param  list<HistoricalRelationship>  $relationships
     * @param  list<Provenance>  $provenance
     */
    public function __construct(
        public string $id,
        public string $sourceReference,
        public string $sourceName,
        public string $resourceReference,
        public DateTimeImmutable $observedAt,
        public DateTimeImmutable $ingestedAt,
        public string $payload,
        public string $payloadHash,
        public string $contentType,
        public array $metadata,
        public array $texts,
        public array $associations,
        public array $relationships,
        public mixed $discoveries,
        public array $provenance,
    ) {}

    public function type(): HistoricalRecordType
    {
        return HistoricalRecordType::Observed;
    }

    public function reference(): CrossPackageReference
    {
        return new CrossPackageReference('sifrious/funes', 'observation', $this->id, $this->payloadHash);
    }

    /**
     * @return list<MetadataAssertion>
     */
    public function metadata(string $namespace, ?string $schemaVersion = null): array
    {
        return array_values(array_filter(
            $this->metadata,
            fn (MetadataAssertion $metadata): bool => $metadata->namespace === $namespace
                && ($schemaVersion === null || $metadata->schemaVersion === $schemaVersion),
        ));
    }

    /**
     * @return list<TextAssertion>
     */
    public function text(string $kind): array
    {
        return array_values(array_filter(
            $this->texts,
            fn (TextAssertion $text): bool => $text->kind === $kind,
        ));
    }

    /**
     * @return list<EntityAssociation>
     */
    public function associated(EntityAssociationRole $role, ?string $entityType = null): array
    {
        return array_values(array_filter(
            $this->associations,
            fn (EntityAssociation $association): bool => $association->role === $role
                && ($entityType === null || $association->entity->type === $entityType),
        ));
    }

    /**
     * @return list<HistoricalRelationship>
     */
    public function related(?HistoricalRelationshipType $type = null): array
    {
        return array_values(array_filter(
            $this->relationships,
            fn (HistoricalRelationship $relationship): bool => $type === null || $relationship->type === $type,
        ));
    }
}
