<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

final readonly class ExternalIdentity
{
    /**
     * @param  list<Provenance>  $provenance
     */
    public function __construct(
        public string $id,
        public string $sourceReference,
        public string $externalIdentifier,
        public array $provenance,
    ) {}
}
