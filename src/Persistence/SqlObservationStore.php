<?php

declare(strict_types=1);

namespace Sifrious\Funes\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use JsonException;
use Sifrious\Funes\Value\AcceptedObservation;
use Sifrious\Funes\Value\Discovery;
use Sifrious\Funes\Value\DiscoveryProvenance;
use Sifrious\Funes\Value\ExtractionDraft;
use Sifrious\Funes\Value\ExtractionResult;
use Sifrious\Funes\Value\Observation;
use Sifrious\Funes\Value\ObservationDisposition;
use Sifrious\Funes\Value\ObservationDraft;
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
            $metadata = $this->json($draft->metadata);
            $fingerprint = hash('sha256', $sourceId.$resourceId.$payloadHash);
            $now = new DateTimeImmutable;

            $this->connection->table('funes_payloads')->insertOrIgnore([
                'hash' => $payloadHash,
                'contents' => $draft->payload,
                'byte_size' => strlen($draft->payload),
                'created_at' => $now,
            ]);

            $id = (string) Str::ulid();
            $inserted = $this->connection->table('funes_observations')->insertOrIgnore([
                'id' => $id,
                'source_id' => $sourceId,
                'resource_id' => $resourceId,
                'payload_hash' => $payloadHash,
                'content_type' => $draft->contentType,
                'fingerprint' => $fingerprint,
                'metadata' => $metadata,
                'observed_at' => $draft->observedAt,
                'ingested_at' => $now,
            ]);

            $row = $this->observationRow($resourceId, $payloadHash);
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

            $result = $draft->result === null ? null : $this->json($draft->result);
            $fingerprint = hash('sha256', $this->json([$result, $draft->failure]));
            $id = (string) Str::ulid();
            $now = new DateTimeImmutable;
            $inserted = $this->connection->table('funes_extractions')->insertOrIgnore([
                'id' => $id,
                'observation_id' => $draft->observationId,
                'extractor' => $draft->extractor,
                'version' => $draft->version,
                'status' => $draft->failure === null ? 'succeeded' : 'failed',
                'result' => $result,
                'failure' => $draft->failure,
                'fingerprint' => $fingerprint,
                'recorded_at' => $now,
            ]);

            $row = $this->connection->table('funes_extractions')
                ->where('observation_id', $draft->observationId)
                ->where('extractor', $draft->extractor)
                ->where('version', $draft->version)
                ->first();

            if (! $row instanceof stdClass) {
                throw new ObservationConflict('The extraction could not be recorded.');
            }

            if (! $inserted && $row->fingerprint !== $fingerprint) {
                throw new ObservationConflict("Extraction [{$draft->extractor}:{$draft->version}] was already recorded with different content.");
            }

            return $this->hydrateExtraction($row);
        }, 3);
    }

    private function sourceId(string $reference, string $name): string
    {
        $this->connection->table('funes_sources')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'reference' => $reference,
            'name' => $name,
            'created_at' => new DateTimeImmutable,
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
            'created_at' => new DateTimeImmutable,
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
                'created_at' => $now,
            ]);
        }
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
        $discoveries = $this->connection->table('funes_discoveries as discoveries')
            ->join('funes_resources as resources', 'resources.id', '=', 'discoveries.resource_id')
            ->where('discoveries.observation_id', $row->id)
            ->orderBy('discoveries.id')
            ->get(['resources.canonical_reference', 'discoveries.relationship'])
            ->map(fn (stdClass $item): Discovery => new Discovery($item->canonical_reference, $item->relationship))
            ->all();

        if (! $source instanceof stdClass || ! $resource instanceof stdClass || ! $payload instanceof stdClass) {
            throw new ObservationConflict('The accepted observation is incomplete.');
        }

        return new Observation(
            (string) $row->id,
            (string) $source->reference,
            (string) $source->name,
            (string) $resource->canonical_reference,
            new DateTimeImmutable((string) $row->observed_at),
            new DateTimeImmutable((string) $row->ingested_at),
            (string) $payload->contents,
            (string) $row->payload_hash,
            (string) $row->content_type,
            $this->decode((string) $row->metadata),
            $discoveries,
        );
    }

    private function hydrateExtraction(stdClass $row): ExtractionResult
    {
        return new ExtractionResult(
            (string) $row->id,
            (string) $row->observation_id,
            (string) $row->extractor,
            (string) $row->version,
            $row->result === null ? null : $this->decode((string) $row->result),
            $row->failure === null ? null : (string) $row->failure,
            new DateTimeImmutable((string) $row->recorded_at),
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
