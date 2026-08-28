<?php

declare(strict_types=1);

namespace Sifrious\Funes\Relationship;

use InvalidArgumentException;
use Sifrious\Funes\Reference\CrossPackageReference;

final readonly class HistoricalRelationshipDraft
{
    public function __construct(
        public HistoricalRelationshipType $type,
        public CrossPackageReference $target,
    ) {
        if (! in_array($target->type, ['observation', 'derived-record', 'historical-event'], true)) {
            throw new InvalidArgumentException('Historical relationships must target historical event references.');
        }
    }
}
