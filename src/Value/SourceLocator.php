<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

use InvalidArgumentException;

final readonly class SourceLocator
{
    public function __construct(
        public string $sourceReference,
        public string $sourceName,
        public string $resourceReference,
    ) {
        if (trim($sourceReference) === '' || trim($sourceName) === '' || trim($resourceReference) === '') {
            throw new InvalidArgumentException('A source locator requires a source reference, source name, and resource reference.');
        }
    }
}
