<?php

declare(strict_types=1);

namespace Sifrious\Funes\Graph;

use InvalidArgumentException;
use Sifrious\Funes\Value\ExternalIdentityClaim;

final readonly class HistoricalIdentifierDraft
{
    public function __construct(
        public string $entityKey,
        public ExternalIdentityClaim $identity,
    ) {
        if (trim($entityKey) === '') {
            throw new InvalidArgumentException('Historical identifier entity keys are required.');
        }
    }
}
