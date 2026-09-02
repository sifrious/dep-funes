<?php

declare(strict_types=1);

namespace Sifrious\Funes\Reference;

use InvalidArgumentException;
use JsonException;
use Sifrious\ReferenceContract\CrossPackageReference;

final readonly class ReferenceSnapshot
{
    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws JsonException
     */
    public function __construct(
        public CrossPackageReference $reference,
        public string $label,
        public array $attributes = [],
    ) {
        if (trim($label) === '') {
            throw new InvalidArgumentException('Reference snapshot labels must be non-empty.');
        }

        json_encode($attributes, JSON_THROW_ON_ERROR);
    }
}
