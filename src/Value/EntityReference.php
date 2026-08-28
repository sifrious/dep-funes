<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

use InvalidArgumentException;

final readonly class EntityReference
{
    public function __construct(
        public EntityKind $kind,
        public string $id,
    ) {
        if (preg_match('/^[a-z][a-z0-9+.-]*:[^\s:][^\s]*$/', $id) !== 1) {
            throw new InvalidArgumentException('Entity references must be namespaced opaque identifiers.');
        }
    }

    /**
     * @return array{kind: string, id: string}
     */
    public function toArray(): array
    {
        return ['kind' => $this->kind->value, 'id' => $this->id];
    }
}
