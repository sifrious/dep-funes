<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

use DateTimeImmutable;

final readonly class ExtractionResult
{
    /**
     * @param  list<ProducerContext>  $producerContexts
     * @param  array<string, mixed>  $failureDetails
     */
    public function __construct(
        public string $id,
        public string $observationId,
        public string $representationType,
        public string $extractor,
        public string $version,
        public string $inputHash,
        public ExtractionDisposition $disposition,
        public array $producerContexts,
        public mixed $result,
        public ?string $failure,
        public ?string $failureCode,
        public array $failureDetails,
        public DateTimeImmutable $recordedAt,
    ) {}

    public function succeeded(): bool
    {
        return $this->disposition === ExtractionDisposition::Succeeded;
    }

    public function type(): HistoricalRecordType
    {
        return HistoricalRecordType::Derived;
    }

    public function process(): DerivationProcess
    {
        return new DerivationProcess($this->extractor, $this->version);
    }
}
