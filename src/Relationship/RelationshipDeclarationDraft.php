<?php

declare(strict_types=1);

namespace Sifrious\Funes\Relationship;

use InvalidArgumentException;

final readonly class RelationshipDeclarationDraft
{
    public function __construct(
        public string $sourceLocator,
        public string $declaredValue,
    ) {
        if (preg_match('/^[a-z][a-z0-9+.-]*:[^\s:][^\s]*$/', $sourceLocator) !== 1) {
            throw new InvalidArgumentException('Relationship declaration locators must be namespaced source fields.');
        }

        if (trim($declaredValue) === '') {
            throw new InvalidArgumentException('Relationship declarations must preserve the non-empty source value.');
        }
    }
}
