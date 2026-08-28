<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ObservationDraft
{
    /**
     * @var list<MetadataDraft>
     */
    public array $metadata;

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
        public mixed $discoveries = [],
    ) {
        new SourceLocator($sourceReference, $sourceName, $resourceReference);
        new Producer($producerReference, $producerName);
        new IngestionRun($ingestionRunReference);

        if ($occurredAt !== null && $occurredAt > $observedAt) {
            throw new InvalidArgumentException('Occurrence time cannot be later than observation time.');
        }

        if (! is_array($metadata) || ! is_array($discoveries)) {
            throw new InvalidArgumentException('Metadata and discoveries must be arrays.');
        }

        $metadataItems = [];

        foreach ($metadata as $item) {
            if (! $item instanceof MetadataDraft) {
                throw new InvalidArgumentException('Metadata must contain only MetadataDraft values.');
            }

            $metadataItems[] = $item;
        }

        $this->metadata = $metadataItems;

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
            $this->discoveries,
        );
    }
}
