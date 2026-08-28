<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

use DateTimeImmutable;

final readonly class Observation
{
    /**
     * @param  list<MetadataAssertion>  $metadata
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
        public mixed $discoveries,
        public array $provenance,
    ) {}

    public function type(): HistoricalRecordType
    {
        return HistoricalRecordType::Observed;
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
}
