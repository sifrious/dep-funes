<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

final readonly class StableEntity
{
    /**
     * @param  list<ExternalIdentity>  $identities
     */
    public function __construct(
        public EntityReference $reference,
        public array $identities,
    ) {}
}
