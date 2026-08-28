<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

use InvalidArgumentException;
use JsonException;

final readonly class MetadataDraft
{
    public function __construct(
        public string $namespace,
        public string $schemaVersion,
        public mixed $attributes,
    ) {
        if (preg_match('/^[a-z][a-z0-9+.-]*:[^\s:][^\s]*$/', $namespace) !== 1) {
            throw new InvalidArgumentException('Metadata namespaces must be namespaced opaque identifiers.');
        }

        if (trim($schemaVersion) === '' || ! is_array($attributes)) {
            throw new InvalidArgumentException('Metadata requires a schema version and structured attributes.');
        }

        try {
            json_encode($attributes, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Metadata attributes must be JSON encodable.', previous: $exception);
        }
    }
}
