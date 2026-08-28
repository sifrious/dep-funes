<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

use InvalidArgumentException;

final readonly class IngestionRun
{
    public function __construct(public string $reference)
    {
        if (trim($reference) === '') {
            throw new InvalidArgumentException('An ingestion run requires a stable reference.');
        }
    }
}
