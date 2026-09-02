<?php

declare(strict_types=1);

namespace Sifrious\Funes\Graph;

use InvalidArgumentException;
use Sifrious\Funes\Value\ExternalIdentityClaim;

final readonly class HistoricalEntityDraft
{
    public function __construct(
        public string $key,
        public ExternalIdentityClaim $identity,
    ) {
        if (trim($key) === '') {
            throw new InvalidArgumentException('Historical entity keys are required.');
        }
    }
}
