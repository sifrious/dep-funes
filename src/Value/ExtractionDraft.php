<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

use InvalidArgumentException;

final readonly class ExtractionDraft
{
    /** @param array<string, mixed> $failureDetails */
    public function __construct(
        public string $observationId,
        public string $extractor,
        public string $version,
        public ProducerContext $producerContext,
        public mixed $result = null,
        public ?string $failure = null,
        public ?string $representationType = null,
        public ?ExtractionDisposition $disposition = null,
        public ?string $failureCode = null,
        public array $failureDetails = [],
    ) {
        new DerivationProcess($extractor, $version);

        if (($result === null) === ($failure === null) || ($result !== null && ! is_array($result))) {
            throw new InvalidArgumentException('An extraction must contain either a result or a failure.');
        }

        if ($representationType !== null && preg_match('/^[a-z][a-z0-9._-]*$/', $representationType) !== 1) {
            throw new InvalidArgumentException('An extraction representation type must be a stable lowercase identifier.');
        }

        $resolvedDisposition = $disposition ?? ($failure === null ? ExtractionDisposition::Succeeded : ExtractionDisposition::Failed);
        if (($resolvedDisposition === ExtractionDisposition::Succeeded) !== ($failure === null)) {
            throw new InvalidArgumentException('Successful extractions require a result; failed and unsupported extractions require failure evidence.');
        }

        if ($failureCode !== null && preg_match('/^[a-z][a-z0-9._-]*$/', $failureCode) !== 1) {
            throw new InvalidArgumentException('Failed and unsupported extractions require a stable failure code.');
        }
    }

    public function resolvedRepresentationType(): string
    {
        return $this->representationType ?? $this->extractor;
    }

    public function resolvedDisposition(): ExtractionDisposition
    {
        return $this->disposition ?? ($this->failure === null ? ExtractionDisposition::Succeeded : ExtractionDisposition::Failed);
    }

    public function resolvedFailureCode(): ?string
    {
        return $this->failure === null ? null : ($this->failureCode ?? 'extraction_failed');
    }
}
