<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

use DateTimeImmutable;

final readonly class Observation
{
    /**
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
        public mixed $metadata,
        public mixed $discoveries,
        public array $provenance,
    ) {}

    public function type(): HistoricalRecordType
    {
        return HistoricalRecordType::Observed;
    }
}
