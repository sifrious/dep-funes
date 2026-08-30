<?php

declare(strict_types=1);

namespace Sifrious\Funes\Correction;

use DateTimeImmutable;
use InvalidArgumentException;
use Sifrious\Funes\Relationship\HistoricalRelationshipType;

final readonly class CorrectionDraft
{
    /**
     * @param  list<string>  $transformationLineage
     */
    public function __construct(
        public string $idempotencyKey,
        public string $payload,
        public string $producerReference,
        public string $producerName,
        public string $ingestionRunReference,
        public DateTimeImmutable $observedAt,
        public HistoricalRelationshipType $relationType = HistoricalRelationshipType::Corrects,
        public ?DateTimeImmutable $occurredAt = null,
        public array $transformationLineage = [],
        public string $contentType = 'application/octet-stream',
    ) {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('A correction must carry an idempotency key.');
        }

        if (! in_array($relationType, [HistoricalRelationshipType::Corrects, HistoricalRelationshipType::Supersedes], true)) {
            throw new InvalidArgumentException('Corrections may only use corrects or supersedes relationships.');
        }
    }
}
