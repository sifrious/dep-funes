<?php

declare(strict_types=1);

namespace Sifrious\Funes\Relationship;

use InvalidArgumentException;
use Sifrious\ReferenceContract\CrossPackageReference;

final readonly class HistoricalRelationshipDraft
{
    public function __construct(
        public HistoricalRelationshipType $type,
        public CrossPackageReference $target,
        public ?RelationshipDeclarationDraft $declaration = null,
    ) {
        if (! in_array($target->type, ['observation', 'derived-record', 'historical-event'], true)) {
            throw new InvalidArgumentException('Historical relationships must target historical event references.');
        }

        if ($type->requiresDeclaration() && $declaration === null) {
            throw new InvalidArgumentException('Causal and parent relationships require an explicit source declaration.');
        }
    }
}
