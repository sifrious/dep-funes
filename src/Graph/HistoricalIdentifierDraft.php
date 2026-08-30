<?php

declare(strict_types=1);

namespace Sifrious\Funes\Graph;

use InvalidArgumentException;

final readonly class HistoricalIdentifierDraft
{
    public function __construct(
        public string $entityReference,
        public string $namespace,
        public string $value,
    ) {
        if (trim($entityReference) === '' || trim($namespace) === '' || trim($value) === '') {
            throw new InvalidArgumentException('Historical identifier entity, namespace, and value are required.');
        }
    }
}
