<?php

declare(strict_types=1);

namespace Sifrious\Funes\Correction;

use Sifrious\Funes\Acceptance\AcceptanceGateway;
use Sifrious\Funes\Acceptance\AcceptanceResult;
use Sifrious\Funes\Acceptance\Submission;
use Sifrious\Funes\Persistence\ObservationNotFound;
use Sifrious\Funes\Persistence\ObservationStore;
use Sifrious\Funes\Relationship\HistoricalRelationshipDraft;
use Sifrious\Funes\Value\ObservationDraft;

final readonly class CorrectionService
{
    public function __construct(
        private AcceptanceGateway $acceptance,
        private ObservationStore $observations,
    ) {}

    public function apply(string $originalObservationId, CorrectionDraft $correction): AcceptanceResult
    {
        $original = $this->observations->get($originalObservationId);

        if ($original === null) {
            throw new ObservationNotFound("Observation [{$originalObservationId}] does not exist.");
        }

        return $this->acceptance->accept(new Submission(
            $correction->idempotencyKey,
            new ObservationDraft(
                sourceReference: $original->sourceReference,
                sourceName: $original->sourceName,
                resourceReference: $original->resourceReference,
                producerReference: $correction->producerReference,
                producerName: $correction->producerName,
                ingestionRunReference: $correction->ingestionRunReference,
                observedAt: $correction->observedAt,
                payload: $correction->payload,
                transformationLineage: $correction->transformationLineage,
                contentType: $correction->contentType,
                relationships: [
                    new HistoricalRelationshipDraft(
                        $correction->relationType,
                        $original->reference(),
                    ),
                ],
            ),
            $correction->occurredAt,
        ));
    }
}
