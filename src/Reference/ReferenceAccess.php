<?php

declare(strict_types=1);

namespace Sifrious\Funes\Reference;

use Sifrious\ReferenceContract\CrossPackageReference;

use JsonException;

final readonly class ReferenceAccess
{
    /**
     * @param  array<string, mixed>  $claims
     *
     * @throws JsonException
     */
    public function __construct(
        public CrossPackageReference $principal,
        public array $claims = [],
    ) {
        json_encode($claims, JSON_THROW_ON_ERROR);
    }
}
