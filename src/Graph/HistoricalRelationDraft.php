<?php

declare(strict_types=1);

namespace Sifrious\Funes\Graph;

use InvalidArgumentException;

final readonly class HistoricalRelationDraft
{
    /**
     * @param  list<string>  $evidenceReferences
     */
    public function __construct(
        public string $subjectKey,
        public string $predicate,
        public string $objectKey,
        public string $sourceReference,
        public AssertionType $assertionType,
        public array $evidenceReferences = [],
        public ?float $confidence = null,
        public ?string $occurredAt = null,
    ) {
        if (trim($subjectKey) === '' || trim($predicate) === '' || trim($objectKey) === '' || trim($sourceReference) === '') {
            throw new InvalidArgumentException('Historical relation subject, predicate, object, and source are required.');
        }

        if ($assertionType === AssertionType::Inferred && ($confidence === null || $confidence < 0 || $confidence > 1 || $evidenceReferences === [])) {
            throw new InvalidArgumentException('Inferred relations require evidence and confidence from zero to one.');
        }
    }
}
