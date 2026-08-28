<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

use DateTimeImmutable;

final readonly class ExtractionResult
{
    /**
     * @param  list<ProducerContext>  $producerContexts
     */
    public function __construct(
        public string $id,
        public string $observationId,
        public string $extractor,
        public string $version,
        public array $producerContexts,
        public mixed $result,
        public ?string $failure,
        public DateTimeImmutable $recordedAt,
    ) {}

    public function succeeded(): bool
    {
        return $this->failure === null;
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
