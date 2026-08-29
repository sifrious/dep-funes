<?php

declare(strict_types=1);

namespace Sifrious\Funes\Persistence;

use Sifrious\Funes\Association\EntityAssociation;
use Sifrious\ReferenceContract\CrossPackageReference;
use Sifrious\Funes\Relationship\HistoricalRelationship;
use Sifrious\Funes\Value\AcceptedObservation;
use Sifrious\Funes\Value\DiscoveryProvenance;
use Sifrious\Funes\Value\ExtractionDraft;
use Sifrious\Funes\Value\ExtractionResult;
use Sifrious\Funes\Value\Observation;
use Sifrious\Funes\Value\ObservationDraft;

interface ObservationStore
{
    public function accept(ObservationDraft $draft): AcceptedObservation;

    public function find(string $sourceReference, string $resourceReference): ?Observation;

    public function get(string $observationId): ?Observation;

    /**
     * @return list<EntityAssociation>
     */
    public function associationsTo(CrossPackageReference $entity): array;

    /**
     * @return list<HistoricalRelationship>
     */
    public function relationshipsTo(CrossPackageReference $event): array;

    /**
     * @return list<DiscoveryProvenance>
     */
    public function discoveriesTo(string $sourceReference, string $resourceReference): array;

    public function recordExtraction(ExtractionDraft $draft): ExtractionResult;
}
