<?php

declare(strict_types=1);

namespace Sifrious\Funes\Identity;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use JsonException;
use Sifrious\Funes\Value\EntityKind;
use Sifrious\Funes\Value\EntityReference;
use Sifrious\Funes\Value\ExternalIdentity;
use Sifrious\Funes\Value\ExternalIdentityClaim;
use Sifrious\Funes\Value\Producer;
use Sifrious\Funes\Value\Provenance;
use Sifrious\Funes\Value\SourceLocator;
use Sifrious\Funes\Value\StableEntity;
use stdClass;

final readonly class SqlIdentityRegistry implements IdentityRegistry
{
    public function __construct(private ConnectionInterface $connection) {}

    public function resolve(ExternalIdentityClaim $claim): StableEntity
    {
        return $this->connection->transaction(function () use ($claim): StableEntity {
            $this->evidence($claim);
            $identifierHash = hash('sha256', $claim->externalIdentifier);
            $external = $this->externalIdentity($claim->kind, $claim->sourceReference, $identifierHash);

            if ($external === null) {
                $external = $this->createExternalIdentity($claim, $identifierHash);
            }

            if ($external->external_identifier !== $claim->externalIdentifier) {
                throw new IdentityConflict('An external identity hash resolved to different source content.');
            }

            $this->connection->table('funes_identity_provenance')->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'external_identity_id' => $external->id,
                'provenance_id' => $claim->provenanceId,
                'recorded_at' => new DateTimeImmutable,
            ]);

            $entity = $this->stableEntity((string) $external->entity_id);

            if ($entity === null) {
                throw new IdentityConflict('The stable entity could not be resolved after identity acceptance.');
            }

            return $entity;
        }, 3);
    }

    public function get(EntityReference $reference): ?StableEntity
    {
        if (! str_starts_with($reference->id, 'funes:')) {
            return null;
        }

        $entity = $this->stableEntity(substr($reference->id, 6));

        return $entity?->reference->kind === $reference->kind ? $entity : null;
    }

    public function find(EntityKind $kind, string $sourceReference, string $externalIdentifier): ?StableEntity
    {
        $external = $this->externalIdentity($kind, $sourceReference, hash('sha256', $externalIdentifier));

        if ($external === null || $external->external_identifier !== $externalIdentifier) {
            return null;
        }

        return $this->stableEntity((string) $external->entity_id);
    }

    private function evidence(ExternalIdentityClaim $claim): stdClass
    {
        $evidence = $this->connection->table('funes_observation_provenance as provenance')
            ->join('funes_sources as sources', 'sources.id', '=', 'provenance.source_id')
            ->where('provenance.id', $claim->provenanceId)
            ->where('sources.reference', $claim->sourceReference)
            ->select('provenance.*')
            ->first();

        if (! $evidence instanceof stdClass) {
            throw new IdentityEvidenceNotFound("Provenance [{$claim->provenanceId}] does not belong to source [{$claim->sourceReference}].");
        }

        return $evidence;
    }

    private function externalIdentity(EntityKind $kind, string $sourceReference, string $identifierHash): ?stdClass
    {
        $identity = $this->connection->table('funes_external_identities')
            ->where('kind', $kind->value)
            ->where('source_reference', $sourceReference)
            ->where('external_identifier_hash', $identifierHash)
            ->first();

        return $identity instanceof stdClass ? $identity : null;
    }

    private function createExternalIdentity(ExternalIdentityClaim $claim, string $identifierHash): stdClass
    {
        $entityId = (string) Str::ulid();
        $now = new DateTimeImmutable;

        $this->connection->table('funes_entities')->insert([
            'id' => $entityId,
            'kind' => $claim->kind->value,
            'created_at' => $now,
        ]);

        $inserted = $this->connection->table('funes_external_identities')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'entity_id' => $entityId,
            'kind' => $claim->kind->value,
            'source_reference' => $claim->sourceReference,
            'external_identifier' => $claim->externalIdentifier,
            'external_identifier_hash' => $identifierHash,
            'created_at' => $now,
        ]);

        if (! $inserted) {
            $this->connection->table('funes_entities')->where('id', $entityId)->delete();
        }

        $external = $this->externalIdentity($claim->kind, $claim->sourceReference, $identifierHash);

        if ($external === null) {
            throw new IdentityConflict('The external identity could not be resolved after creation.');
        }

        return $external;
    }

    private function stableEntity(string $entityId): ?StableEntity
    {
        $entity = $this->connection->table('funes_entities')->where('id', $entityId)->first();

        if (! $entity instanceof stdClass) {
            return null;
        }

        $identities = array_values($this->connection->table('funes_external_identities')
            ->where('entity_id', $entityId)
            ->orderBy('id')
            ->get()
            ->map(fn (stdClass $identity): ExternalIdentity => new ExternalIdentity(
                (string) $identity->id,
                (string) $identity->source_reference,
                (string) $identity->external_identifier,
                $this->identityProvenance((string) $identity->id),
            ))
            ->all());

        return new StableEntity(
            new EntityReference(EntityKind::from((string) $entity->kind), 'funes:'.$entity->id),
            $identities,
        );
    }

    /**
     * @return list<Provenance>
     */
    private function identityProvenance(string $externalIdentityId): array
    {
        return array_values($this->connection->table('funes_identity_provenance as evidence')
            ->join('funes_observation_provenance as provenance', 'provenance.id', '=', 'evidence.provenance_id')
            ->join('funes_sources as sources', 'sources.id', '=', 'provenance.source_id')
            ->join('funes_resources as resources', 'resources.id', '=', 'provenance.resource_id')
            ->where('evidence.external_identity_id', $externalIdentityId)
            ->orderBy('evidence.recorded_at')
            ->orderBy('evidence.id')
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
                $item->occurred_at === null ? null : new DateTimeImmutable((string) $item->occurred_at),
                new DateTimeImmutable((string) $item->observed_at),
                new DateTimeImmutable((string) $item->recorded_at),
                $this->decode((string) $item->transformation_lineage),
            ))
            ->all());
    }

    /**
     * @return list<string>
     */
    private function decode(string $value): array
    {
        $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new JsonException('Stored transformation lineage must decode to a list.');
        }

        $lineage = [];

        foreach ($decoded as $reference) {
            if (! is_string($reference)) {
                throw new JsonException('Stored transformation lineage must contain only string references.');
            }

            $lineage[] = $reference;
        }

        return $lineage;
    }
}
