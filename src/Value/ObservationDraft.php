<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

use DateTimeImmutable;
use InvalidArgumentException;
use Sifrious\Funes\Association\EntityAssociationDraft;
use Sifrious\Funes\Relationship\HistoricalRelationshipDraft;

final readonly class ObservationDraft
{
    /**
     * @var list<MetadataDraft>
     */
    public array $metadata;

    /**
     * @var list<TextDraft>
     */
    public array $texts;

    /**
     * @var list<EntityAssociationDraft>
     */
    public array $associations;

    /**
     * @var list<HistoricalRelationshipDraft>
     */
    public array $relationships;

    /**
     * @param  list<string>  $transformationLineage
     */
    public function __construct(
        public string $sourceReference,
        public string $sourceName,
        public string $resourceReference,
        public string $producerReference,
        public string $producerName,
        public string $ingestionRunReference,
        public DateTimeImmutable $observedAt,
        public string $payload,
        public ?DateTimeImmutable $occurredAt = null,
        public array $transformationLineage = [],
        public string $contentType = 'application/octet-stream',
        mixed $metadata = [],
        mixed $texts = [],
        mixed $associations = [],
        mixed $relationships = [],
        public mixed $discoveries = [],
    ) {
        new SourceLocator($sourceReference, $sourceName, $resourceReference);
        new Producer($producerReference, $producerName);
        new IngestionRun($ingestionRunReference);

        if ($occurredAt !== null && $occurredAt > $observedAt) {
            throw new InvalidArgumentException('Occurrence time cannot be later than observation time.');
        }

        if (! is_array($metadata) || ! is_array($texts) || ! is_array($associations) || ! is_array($relationships) || ! is_array($discoveries)) {
            throw new InvalidArgumentException('Metadata, texts, associations, relationships, and discoveries must be arrays.');
        }

        $metadataItems = [];

        foreach ($metadata as $item) {
            if (! $item instanceof MetadataDraft) {
                throw new InvalidArgumentException('Metadata must contain only MetadataDraft values.');
            }

            $metadataItems[] = $item;
        }

        $this->metadata = $metadataItems;

        $textItems = [];

        foreach ($texts as $text) {
            if (! $text instanceof TextDraft) {
                throw new InvalidArgumentException('Texts must contain only TextDraft values.');
            }

            $textItems[] = $text;
        }

        $this->texts = $textItems;

        $associationItems = [];

        foreach ($associations as $association) {
            if (! $association instanceof EntityAssociationDraft) {
                throw new InvalidArgumentException('Associations must contain only EntityAssociationDraft values.');
            }

            $associationItems[] = $association;
        }

        $this->associations = $associationItems;

        $relationshipItems = [];

        foreach ($relationships as $relationship) {
            if (! $relationship instanceof HistoricalRelationshipDraft) {
                throw new InvalidArgumentException('Relationships must contain only HistoricalRelationshipDraft values.');
            }

            $relationshipItems[] = $relationship;
        }

        $this->relationships = $relationshipItems;

        foreach ($discoveries as $discovery) {
            if (! $discovery instanceof Discovery) {
                throw new InvalidArgumentException('Discoveries must contain only Discovery values.');
            }
        }

        foreach ($transformationLineage as $transformation) {
            if (trim($transformation) === '') {
                throw new InvalidArgumentException('Transformation lineage must contain only non-empty stable references.');
            }
        }
    }

    public function withOccurredAt(DateTimeImmutable $occurredAt): self
    {
        return new self(
            $this->sourceReference,
            $this->sourceName,
            $this->resourceReference,
            $this->producerReference,
            $this->producerName,
            $this->ingestionRunReference,
            $this->observedAt,
            $this->payload,
            $occurredAt,
            $this->transformationLineage,
            $this->contentType,
            $this->metadata,
            $this->texts,
            $this->associations,
            $this->relationships,
            $this->discoveries,
        );
    }
}
