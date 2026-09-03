<?php

declare(strict_types=1);

namespace Sifrious\Funes\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use JsonException;
use Sifrious\Funes\Association\EntityAssociation;
use Sifrious\Funes\Association\EntityAssociationDraft;
use Sifrious\Funes\Association\EntityAssociationRole;
use Sifrious\Funes\Relationship\HistoricalRelationship;
use Sifrious\Funes\Relationship\HistoricalRelationshipDraft;
use Sifrious\Funes\Relationship\HistoricalRelationshipType;
use Sifrious\Funes\Relationship\RelationshipDeclaration;
use Sifrious\Funes\Time\StoredTimestamp;
use Sifrious\Funes\Value\AcceptedObservation;
use Sifrious\Funes\Value\Discovery;
use Sifrious\Funes\Value\DiscoveryProvenance;
use Sifrious\Funes\Value\ExtractionDisposition;
use Sifrious\Funes\Value\ExtractionDraft;
use Sifrious\Funes\Value\ExtractionResult;
use Sifrious\Funes\Value\IngestionRun;
use Sifrious\Funes\Value\MetadataAssertion;
use Sifrious\Funes\Value\MetadataDraft;
use Sifrious\Funes\Value\Observation;
use Sifrious\Funes\Value\ObservationDisposition;
use Sifrious\Funes\Value\ObservationDraft;
use Sifrious\Funes\Value\Producer;
use Sifrious\Funes\Value\ProducerContext;
use Sifrious\Funes\Value\Provenance;
use Sifrious\Funes\Value\SourceLocator;
use Sifrious\Funes\Value\TextAssertion;
use Sifrious\Funes\Value\TextDraft;
use Sifrious\ReferenceContract\CrossPackageReference;
use stdClass;

final class SqlObservationStore implements ObservationStore
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function accept(ObservationDraft $draft): AcceptedObservation
    {
        return $this->connection->transaction(function () use ($draft): AcceptedObservation {
            $sourceId = $this->sourceId($draft->sourceReference, $draft->sourceName);
            $resourceId = $this->resourceId($sourceId, $draft->resourceReference);
            $previous = $this->connection->table('funes_observations')
                ->where('resource_id', $resourceId)
                ->orderByDesc('observed_at')
                ->orderByDesc('id')
                ->first();
            $payloadHash = hash('sha256', $draft->payload);
            $fingerprint = hash('sha256', $sourceId.$resourceId.$payloadHash);
            $now = new DateTimeImmutable;

            $this->connection->table('funes_payloads')->insertOrIgnore([
                'hash' => $payloadHash,
                'contents' => $draft->payload,
                'byte_size' => strlen($draft->payload),
                'created_at' => StoredTimestamp::format($now),
            ]);

            $id = (string) Str::ulid();
            $inserted = $this->connection->table('funes_observations')->insertOrIgnore([
                'id' => $id,
                'source_id' => $sourceId,
                'resource_id' => $resourceId,
                'payload_hash' => $payloadHash,
                'content_type' => $draft->contentType,
                'fingerprint' => $fingerprint,
                'metadata' => '[]',
                'observed_at' => StoredTimestamp::format($draft->observedAt),
                'ingested_at' => StoredTimestamp::format($now),
            ]);

            $row = $this->observationRow($resourceId, $payloadHash);
            $provenance = $this->storeProvenance((string) $row->id, $sourceId, $resourceId, $draft, $now);
            $this->storeMetadata((string) $row->id, (string) $provenance->id, $draft->metadata, $now);
            $this->storeText((string) $row->id, (string) $provenance->id, $draft->texts, $now);
            $this->storeAssociations((string) $row->id, (string) $provenance->id, $draft->associations, $now);
            $this->storeRelationships((string) $row->id, (string) $provenance->id, $draft->relationships, $now);
            $this->storeDiscoveries((string) $row->id, $sourceId, $resourceId, $draft->discoveries, $now);

            return new AcceptedObservation(
                $this->hydrateObservation($row),
                match (true) {
                    ! $inserted => ObservationDisposition::Unchanged,
                    $previous === null => ObservationDisposition::First,
                    default => ObservationDisposition::Changed,
                },
            );
        }, 3);
    }

    public function find(string $sourceReference, string $resourceReference): ?Observation
    {
        $row = $this->connection->table('funes_observations as observations')
            ->join('funes_sources as sources', 'sources.id', '=', 'observations.source_id')
            ->join('funes_resources as resources', 'resources.id', '=', 'observations.resource_id')
            ->where('sources.reference', $sourceReference)
            ->where('resources.reference_hash', hash('sha256', $resourceReference))
            ->orderByDesc('observations.observed_at')
            ->orderByDesc('observations.id')
            ->select('observations.*')
            ->first();

        return $row instanceof stdClass ? $this->hydrateObservation($row) : null;
    }

    public function get(string $observationId): ?Observation
    {
        $row = $this->connection->table('funes_observations')->where('id', $observationId)->first();

        return $row instanceof stdClass ? $this->hydrateObservation($row) : null;
    }

    public function associationsTo(CrossPackageReference $entity): array
    {
        return array_values($this->connection->table('funes_entity_associations')
            ->where('entity_reference_key', $entity->key())
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get()
            ->map(fn (stdClass $row): EntityAssociation => $this->hydrateAssociation($row))
            ->all());
    }

    public function relationshipsTo(CrossPackageReference $event): array
    {
        return array_values($this->connection->table('funes_historical_relationships')
            ->where('target_reference_key', $event->key())
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get()
            ->map(fn (stdClass $row): HistoricalRelationship => $this->hydrateRelationship($row))
            ->all());
    }

    public function discoveriesTo(string $sourceReference, string $resourceReference): array
    {
        return array_values($this->connection->table('funes_discoveries as discoveries')
            ->join('funes_resources as resources', 'resources.id', '=', 'discoveries.resource_id')
            ->join('funes_resources as parents', 'parents.id', '=', 'discoveries.parent_resource_id')
            ->join('funes_sources as sources', 'sources.id', '=', 'resources.source_id')
            ->where('sources.reference', $sourceReference)
            ->where('resources.reference_hash', hash('sha256', $resourceReference))
            ->orderBy('discoveries.id')
            ->get([
                'discoveries.observation_id',
                'parents.canonical_reference as parent_reference',
                'resources.canonical_reference as resource_reference',
                'discoveries.relationship',
            ])
            ->map(fn (stdClass $row): DiscoveryProvenance => new DiscoveryProvenance(
                (string) $row->observation_id,
                (string) $row->parent_reference,
                (string) $row->resource_reference,
                (string) $row->relationship,
            ))
            ->all());
    }

    public function recordExtraction(ExtractionDraft $draft): ExtractionResult
    {
        return $this->connection->transaction(function () use ($draft): ExtractionResult {
            if (! $this->connection->table('funes_observations')->where('id', $draft->observationId)->exists()) {
                throw new ObservationNotFound("Observation [{$draft->observationId}] does not exist.");
            }

            $inputHash = (string) $this->connection->table('funes_observations')->where('id', $draft->observationId)->value('payload_hash');
            $result = $draft->result === null ? null : $this->json($draft->result);
            $failureDetails = $draft->failure === null ? null : $this->json($draft->failureDetails);
            $fingerprint = hash('sha256', $this->json([$inputHash, $result, $draft->failure, $draft->resolvedFailureCode(), $failureDetails]));
            $id = (string) Str::ulid();
            $now = new DateTimeImmutable;
            $inserted = $this->connection->table('funes_extractions')->insertOrIgnore([
                'id' => $id,
                'observation_id' => $draft->observationId,
                'representation_type' => $draft->resolvedRepresentationType(),
                'extractor' => $draft->extractor,
                'version' => $draft->version,
                'input_hash' => $inputHash,
                'status' => $draft->resolvedDisposition()->value,
                'result' => $result,
                'failure' => $draft->failure,
                'failure_code' => $draft->resolvedFailureCode(),
                'failure_details' => $failureDetails,
                'fingerprint' => $fingerprint,
                'recorded_at' => StoredTimestamp::format($now),
            ]);

            $row = $this->connection->table('funes_extractions')
                ->where('observation_id', $draft->observationId)
                ->where('representation_type', $draft->resolvedRepresentationType())
                ->where('extractor', $draft->extractor)
                ->where('version', $draft->version)
                ->first();

            if (! $row instanceof stdClass) {
                throw new ObservationConflict('The extraction could not be recorded.');
            }

            if (! $inserted && $row->fingerprint !== $fingerprint) {
                throw new ObservationConflict("Extraction [{$draft->extractor}:{$draft->version}] was already recorded with different content.");
            }

            $this->storeExtractionProvenance((string) $row->id, $draft->producerContext, $now);

            return $this->hydrateExtraction($row);
        }, 3);
    }

    public function extraction(string $observationId, string $representationType, string $extractor, string $version): ?ExtractionResult
    {
        $row = $this->connection->table('funes_extractions')
            ->where('observation_id', $observationId)
            ->where('representation_type', $representationType)
            ->where('extractor', $extractor)
            ->where('version', $version)
            ->first();

        return $row instanceof stdClass ? $this->hydrateExtraction($row) : null;
    }

    public function extractions(string $observationId, ?string $representationType = null): array
    {
        $query = $this->connection->table('funes_extractions')->where('observation_id', $observationId);
        if ($representationType !== null) {
            $query->where('representation_type', $representationType);
        }

        return array_values($query->orderBy('representation_type')->orderBy('extractor')->orderBy('version')->get()
            ->map(fn (stdClass $row): ExtractionResult => $this->hydrateExtraction($row))->all());
    }

    private function sourceId(string $reference, string $name): string
    {
        $this->connection->table('funes_sources')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'reference' => $reference,
            'name' => $name,
            'created_at' => StoredTimestamp::format(new DateTimeImmutable),
        ]);

        return (string) $this->connection->table('funes_sources')->where('reference', $reference)->value('id');
    }

    private function resourceId(string $sourceId, string $reference): string
    {
        $referenceHash = hash('sha256', $reference);
        $this->connection->table('funes_resources')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'source_id' => $sourceId,
            'canonical_reference' => $reference,
            'reference_hash' => $referenceHash,
            'created_at' => StoredTimestamp::format(new DateTimeImmutable),
        ]);

        return (string) $this->connection->table('funes_resources')
            ->where('source_id', $sourceId)
            ->where('reference_hash', $referenceHash)
            ->value('id');
    }

    private function storeDiscoveries(string $observationId, string $sourceId, string $parentId, mixed $discoveries, DateTimeImmutable $now): void
    {
        if (! is_array($discoveries)) {
            throw new ObservationConflict('Stored discoveries must be an array.');
        }

        foreach ($discoveries as $discovery) {
            $resourceId = $this->resourceId($sourceId, $discovery->canonicalReference);
            $this->connection->table('funes_discoveries')->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'observation_id' => $observationId,
                'parent_resource_id' => $parentId,
                'resource_id' => $resourceId,
                'relationship' => $discovery->relationship,
                'created_at' => StoredTimestamp::format($now),
            ]);
        }
    }

    private function storeProvenance(
        string $observationId,
        string $sourceId,
        string $resourceId,
        ObservationDraft $draft,
        DateTimeImmutable $recordedAt,
    ): stdClass {
        $lineage = $this->json($draft->transformationLineage);
        $fingerprint = hash('sha256', $this->json([
            $observationId,
            $sourceId,
            $resourceId,
            $draft->producerReference,
            $draft->ingestionRunReference,
            $draft->occurredAt?->format(DATE_ATOM),
            $draft->observedAt->format(DATE_ATOM),
            $draft->transformationLineage,
        ]));

        $this->connection->table('funes_observation_provenance')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'observation_id' => $observationId,
            'source_id' => $sourceId,
            'resource_id' => $resourceId,
            'producer_reference' => $draft->producerReference,
            'producer_name' => $draft->producerName,
            'ingestion_run_reference' => $draft->ingestionRunReference,
            'occurred_at' => StoredTimestamp::format($draft->occurredAt),
            'observed_at' => StoredTimestamp::format($draft->observedAt),
            'recorded_at' => StoredTimestamp::format($recordedAt),
            'transformation_lineage' => $lineage,
            'fingerprint' => $fingerprint,
        ]);

        $provenance = $this->connection->table('funes_observation_provenance')
            ->where('fingerprint', $fingerprint)
            ->first();

        if (! $provenance instanceof stdClass) {
            throw new ObservationConflict('The observation provenance could not be accepted.');
        }

        return $provenance;
    }

    /**
     * @param  list<MetadataDraft>  $metadata
     */
    private function storeMetadata(
        string $observationId,
        string $provenanceId,
        array $metadata,
        DateTimeImmutable $recordedAt,
    ): void {
        foreach ($metadata as $item) {
            $attributes = $this->json($item->attributes);
            $fingerprint = hash('sha256', $this->json([
                $observationId,
                $provenanceId,
                $item->namespace,
                $item->schemaVersion,
                $item->attributes,
            ]));

            $this->connection->table('funes_observation_metadata')->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'observation_id' => $observationId,
                'provenance_id' => $provenanceId,
                'namespace' => $item->namespace,
                'schema_version' => $item->schemaVersion,
                'attributes' => $attributes,
                'fingerprint' => $fingerprint,
                'recorded_at' => StoredTimestamp::format($recordedAt),
            ]);
        }
    }

    /**
     * @param  list<TextDraft>  $texts
     */
    private function storeText(
        string $observationId,
        string $provenanceId,
        array $texts,
        DateTimeImmutable $recordedAt,
    ): void {
        foreach ($texts as $text) {
            $textHash = hash('sha256', $text->text);
            $fingerprint = hash('sha256', $this->json([
                $observationId,
                $provenanceId,
                $text->kind,
                $text->contentType,
                $text->language,
                $textHash,
            ]));

            $this->connection->table('funes_observation_text')->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'observation_id' => $observationId,
                'provenance_id' => $provenanceId,
                'kind' => $text->kind,
                'content_type' => $text->contentType,
                'language' => $text->language,
                'text' => $text->text,
                'text_hash' => $textHash,
                'fingerprint' => $fingerprint,
                'recorded_at' => StoredTimestamp::format($recordedAt),
            ]);
        }
    }

    /**
     * @param  list<EntityAssociationDraft>  $associations
     */
    private function storeAssociations(
        string $observationId,
        string $provenanceId,
        array $associations,
        DateTimeImmutable $recordedAt,
    ): void {
        foreach ($associations as $association) {
            $reference = $this->json($association->entity->toArray());
            $fingerprint = hash('sha256', $this->json([
                $observationId,
                $association->role->value,
                $association->entity->toArray(),
            ]));

            $this->connection->table('funes_entity_associations')->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'observation_id' => $observationId,
                'role' => $association->role->value,
                'entity_reference' => $reference,
                'entity_reference_key' => $association->entity->key(),
                'fingerprint' => $fingerprint,
                'recorded_at' => StoredTimestamp::format($recordedAt),
            ]);

            $associationId = $this->connection->table('funes_entity_associations')
                ->where('fingerprint', $fingerprint)
                ->value('id');

            if (! is_string($associationId)) {
                throw new ObservationConflict('The entity association could not be accepted.');
            }

            $this->connection->table('funes_entity_association_provenance')->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'association_id' => $associationId,
                'provenance_id' => $provenanceId,
                'recorded_at' => StoredTimestamp::format($recordedAt),
            ]);
        }
    }

    /**
     * @param  list<HistoricalRelationshipDraft>  $relationships
     */
    private function storeRelationships(
        string $observationId,
        string $provenanceId,
        array $relationships,
        DateTimeImmutable $recordedAt,
    ): void {
        foreach ($relationships as $relationship) {
            if ($relationship->target->owner === 'sifrious/funes'
                && $relationship->target->type === 'observation'
                && ! $this->connection->table('funes_observations')->where('id', $relationship->target->id)->exists()) {
                throw new ObservationNotFound("Related observation [{$relationship->target->id}] does not exist.");
            }

            $fingerprint = hash('sha256', $this->json([
                $observationId,
                $relationship->type->value,
                $relationship->target->toArray(),
            ]));

            $this->connection->table('funes_historical_relationships')->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'observation_id' => $observationId,
                'type' => $relationship->type->value,
                'target_reference' => $this->json($relationship->target->toArray()),
                'target_reference_key' => $relationship->target->key(),
                'fingerprint' => $fingerprint,
                'recorded_at' => StoredTimestamp::format($recordedAt),
            ]);

            $relationshipId = $this->connection->table('funes_historical_relationships')
                ->where('fingerprint', $fingerprint)
                ->value('id');

            if (! is_string($relationshipId)) {
                throw new ObservationConflict('The historical relationship could not be accepted.');
            }

            $this->connection->table('funes_historical_relationship_provenance')->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'relationship_id' => $relationshipId,
                'provenance_id' => $provenanceId,
                'recorded_at' => StoredTimestamp::format($recordedAt),
            ]);

            if ($relationship->declaration !== null) {
                $this->connection->table('funes_relationship_declarations')->insertOrIgnore([
                    'id' => (string) Str::ulid(),
                    'relationship_id' => $relationshipId,
                    'provenance_id' => $provenanceId,
                    'source_locator' => $relationship->declaration->sourceLocator,
                    'declared_value' => $relationship->declaration->declaredValue,
                    'fingerprint' => hash('sha256', $this->json([
                        $relationshipId,
                        $provenanceId,
                        $relationship->declaration->sourceLocator,
                        $relationship->declaration->declaredValue,
                    ])),
                    'recorded_at' => StoredTimestamp::format($recordedAt),
                ]);
            }
        }
    }

    private function storeExtractionProvenance(
        string $extractionId,
        ProducerContext $producerContext,
        DateTimeImmutable $recordedAt,
    ): void {
        $this->connection->table('funes_extraction_provenance')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'extraction_id' => $extractionId,
            'producer_reference' => $producerContext->producer->reference,
            'producer_name' => $producerContext->producer->name,
            'ingestion_run_reference' => $producerContext->ingestionRun->reference,
            'recorded_at' => StoredTimestamp::format($recordedAt),
        ]);
    }

    private function observationRow(string $resourceId, string $payloadHash): stdClass
    {
        $row = $this->connection->table('funes_observations')
            ->where('resource_id', $resourceId)
            ->where('payload_hash', $payloadHash)
            ->first();

        if (! $row instanceof stdClass) {
            throw new ObservationConflict('The observation could not be accepted.');
        }

        return $row;
    }

    private function hydrateObservation(stdClass $row): Observation
    {
        $source = $this->connection->table('funes_sources')->where('id', $row->source_id)->first();
        $resource = $this->connection->table('funes_resources')->where('id', $row->resource_id)->first();
        $payload = $this->connection->table('funes_payloads')->where('hash', $row->payload_hash)->first();

        if (! $source instanceof stdClass || ! $resource instanceof stdClass || ! $payload instanceof stdClass) {
            throw new ObservationConflict('The accepted observation is incomplete.');
        }

        $discoveries = $this->connection->table('funes_discoveries as discoveries')
            ->join('funes_resources as resources', 'resources.id', '=', 'discoveries.resource_id')
            ->where('discoveries.observation_id', $row->id)
            ->orderBy('discoveries.id')
            ->get(['resources.canonical_reference', 'discoveries.relationship'])
            ->map(fn (stdClass $item): Discovery => new Discovery($item->canonical_reference, $item->relationship))
            ->all();
        $provenance = array_values($this->connection->table('funes_observation_provenance as provenance')
            ->join('funes_sources as sources', 'sources.id', '=', 'provenance.source_id')
            ->join('funes_resources as resources', 'resources.id', '=', 'provenance.resource_id')
            ->where('provenance.observation_id', $row->id)
            ->orderBy('provenance.recorded_at')
            ->orderBy('provenance.id')
            ->get([
                'provenance.*',
                'sources.reference as source_reference',
                'sources.name as source_name',
                'resources.canonical_reference as resource_reference',
            ])
            ->map(fn (stdClass $item): Provenance => new Provenance(
                (string) $item->id,
                (string) $item->observation_id,
                new SourceLocator(
                    (string) $item->source_reference,
                    (string) $item->source_name,
                    (string) $item->resource_reference,
                ),
                new Producer((string) $item->producer_reference, (string) $item->producer_name),
                new IngestionRun((string) $item->ingestion_run_reference),
                StoredTimestamp::parse($item->occurred_at),
                StoredTimestamp::require($item->observed_at),
                StoredTimestamp::require($item->recorded_at),
                $this->decode((string) $item->transformation_lineage),
            ))
            ->all());
        $metadata = array_values($this->connection->table('funes_observation_metadata')
            ->where('observation_id', $row->id)
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get()
            ->map(fn (stdClass $item): MetadataAssertion => new MetadataAssertion(
                (string) $item->id,
                (string) $item->namespace,
                (string) $item->schema_version,
                $this->decode((string) $item->attributes),
                (string) $item->provenance_id,
                StoredTimestamp::require($item->recorded_at),
            ))
            ->all());
        $legacyMetadata = $this->decode((string) $row->metadata);

        if ($legacyMetadata !== []) {
            array_unshift($metadata, new MetadataAssertion(
                'funes:legacy-metadata/'.$row->id,
                'funes:legacy',
                '1',
                $legacyMetadata,
                $provenance[0]->id ?? null,
                StoredTimestamp::require($row->ingested_at),
            ));
        }
        $texts = array_values($this->connection->table('funes_observation_text')
            ->where('observation_id', $row->id)
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get()
            ->map(fn (stdClass $item): TextAssertion => new TextAssertion(
                (string) $item->id,
                (string) $item->observation_id,
                (string) $item->provenance_id,
                (string) $item->kind,
                (string) $item->content_type,
                (string) $item->text,
                (string) $item->text_hash,
                $item->language === null ? null : (string) $item->language,
                StoredTimestamp::require($item->recorded_at),
            ))
            ->all());

        if (str_starts_with((string) $row->content_type, 'text/') && (string) $payload->contents !== '') {
            array_unshift($texts, new TextAssertion(
                'funes:source-payload/'.$row->id,
                (string) $row->id,
                $provenance[0]->id ?? null,
                'funes:source-payload',
                (string) $row->content_type,
                (string) $payload->contents,
                (string) $row->payload_hash,
                null,
                StoredTimestamp::require($row->ingested_at),
            ));
        }
        $associations = array_values($this->connection->table('funes_entity_associations')
            ->where('observation_id', $row->id)
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get()
            ->map(fn (stdClass $item): EntityAssociation => $this->hydrateAssociation($item))
            ->all());
        $relationships = array_values($this->connection->table('funes_historical_relationships')
            ->where('observation_id', $row->id)
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get()
            ->map(fn (stdClass $item): HistoricalRelationship => $this->hydrateRelationship($item))
            ->all());

        return new Observation(
            (string) $row->id,
            (string) $source->reference,
            (string) $source->name,
            (string) $resource->canonical_reference,
            StoredTimestamp::require($row->observed_at),
            StoredTimestamp::require($row->ingested_at),
            (string) $payload->contents,
            (string) $row->payload_hash,
            (string) $row->content_type,
            $metadata,
            $texts,
            $associations,
            $relationships,
            $discoveries,
            $provenance,
        );
    }

    private function hydrateAssociation(stdClass $row): EntityAssociation
    {
        $reference = $this->decode((string) $row->entity_reference);
        $provenanceIds = array_values($this->connection->table('funes_entity_association_provenance')
            ->where('association_id', $row->id)
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->pluck('provenance_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all());

        return new EntityAssociation(
            (string) $row->id,
            (string) $row->observation_id,
            EntityAssociationRole::from((string) $row->role),
            CrossPackageReference::fromArray($reference),
            $provenanceIds,
            StoredTimestamp::require($row->recorded_at),
        );
    }

    private function hydrateRelationship(stdClass $row): HistoricalRelationship
    {
        $target = $this->decode((string) $row->target_reference);
        $provenanceIds = array_values($this->connection->table('funes_historical_relationship_provenance')
            ->where('relationship_id', $row->id)
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->pluck('provenance_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all());
        $declarations = array_values($this->connection->table('funes_relationship_declarations')
            ->where('relationship_id', $row->id)
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get()
            ->map(fn (stdClass $item): RelationshipDeclaration => new RelationshipDeclaration(
                (string) $item->id,
                (string) $item->provenance_id,
                (string) $item->source_locator,
                (string) $item->declared_value,
                StoredTimestamp::require($item->recorded_at),
            ))
            ->all());

        return new HistoricalRelationship(
            (string) $row->id,
            (string) $row->observation_id,
            HistoricalRelationshipType::from((string) $row->type),
            CrossPackageReference::fromArray($target),
            $provenanceIds,
            $declarations,
            StoredTimestamp::require($row->recorded_at),
        );
    }

    private function hydrateExtraction(stdClass $row): ExtractionResult
    {
        $producerContexts = array_values($this->connection->table('funes_extraction_provenance')
            ->where('extraction_id', $row->id)
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get()
            ->map(fn (stdClass $item): ProducerContext => new ProducerContext(
                new Producer((string) $item->producer_reference, (string) $item->producer_name),
                new IngestionRun((string) $item->ingestion_run_reference),
            ))
            ->all());

        return new ExtractionResult(
            (string) $row->id,
            (string) $row->observation_id,
            (string) $row->representation_type,
            (string) $row->extractor,
            (string) $row->version,
            (string) $row->input_hash,
            ExtractionDisposition::from((string) $row->status),
            $producerContexts,
            $row->result === null ? null : $this->decode((string) $row->result),
            $row->failure === null ? null : (string) $row->failure,
            $row->failure_code === null ? null : (string) $row->failure_code,
            $row->failure_details === null ? [] : $this->decode((string) $row->failure_details),
            StoredTimestamp::require($row->recorded_at),
        );
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function decode(string $value): mixed
    {
        $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new JsonException('Stored JSON must decode to an array.');
        }

        return $decoded;
    }
}
