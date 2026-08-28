<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

use InvalidArgumentException;

final readonly class ExtractionDraft
{
    public function __construct(
        public string $observationId,
        public string $extractor,
        public string $version,
        public mixed $result = null,
        public ?string $failure = null,
    ) {
        new DerivationProcess($extractor, $version);

        if (($result === null) === ($failure === null) || ($result !== null && ! is_array($result))) {
            throw new InvalidArgumentException('An extraction must contain either a result or a failure.');
        }
    }
}
