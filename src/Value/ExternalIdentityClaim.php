<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

use InvalidArgumentException;

final readonly class ExternalIdentityClaim
{
    public function __construct(
        public EntityKind $kind,
        public string $sourceReference,
        public string $externalIdentifier,
        public string $provenanceId,
    ) {
        if (trim($sourceReference) === '' || trim($externalIdentifier) === '' || trim($provenanceId) === '') {
            throw new InvalidArgumentException('An external identity claim requires source, external identifier, and provenance references.');
        }
    }
}
