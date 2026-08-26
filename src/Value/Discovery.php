<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

final readonly class Discovery
{
    public function __construct(
        public string $canonicalReference,
        public string $relationship = 'discovered',
    ) {}
}
