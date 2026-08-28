<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

use InvalidArgumentException;

final readonly class DerivationProcess
{
    public function __construct(
        public string $name,
        public string $version,
    ) {
        if (trim($name) === '' || trim($version) === '') {
            throw new InvalidArgumentException('A derivation process requires a name and version.');
        }
    }
}
