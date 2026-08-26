<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ObservationDraft
{
    public function __construct(
        public string $sourceReference,
        public string $sourceName,
        public string $resourceReference,
        public DateTimeImmutable $observedAt,
        public string $payload,
        public string $mediaType = 'application/octet-stream',
        public mixed $metadata = [],
        public mixed $discoveries = [],
    ) {
        if ($sourceReference === '' || $resourceReference === '') {
            throw new InvalidArgumentException('Source and resource references must not be empty.');
        }

        if (! is_array($metadata) || ! is_array($discoveries)) {
            throw new InvalidArgumentException('Metadata and discoveries must be arrays.');
        }

        foreach ($discoveries as $discovery) {
            if (! $discovery instanceof Discovery) {
                throw new InvalidArgumentException('Discoveries must contain only Discovery values.');
            }
        }
    }
}
