<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

final readonly class DiscoveryProvenance
{
    public function __construct(
        public string $observationId,
        public string $parentResourceReference,
        public string $resourceReference,
        public string $relationship,
    ) {}
}
