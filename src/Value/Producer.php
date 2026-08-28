<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

use InvalidArgumentException;

final readonly class Producer
{
    public function __construct(
        public string $reference,
        public string $name,
    ) {
        if (trim($reference) === '' || trim($name) === '') {
            throw new InvalidArgumentException('A producer requires a stable reference and name.');
        }
    }
}
