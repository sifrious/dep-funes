<?php

declare(strict_types=1);

namespace Sifrious\Funes\Graph;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Sifrious\Funes\Identity\IdentityRegistry;
use Sifrious\Funes\Value\StableEntity;
use stdClass;

final readonly class SqlHistoricalAppender implements HistoricalAppender
{
    public function __construct(private ConnectionInterface $connection, private IdentityRegistry $identities) {}

    public function append(HistoricalAppend $append): void
    {
        $fingerprint = $this->appendFingerprint($append);
        $eventId = $append->idempotencyKey();
        $this->connection->transaction(function () use ($append, $fingerprint, $eventId): void {
            $existing = $this->connection->table('funes_graph_appends')->where('event_id', $eventId)->lockForUpdate()->first();
            if ($existing instanceof stdClass) {
                if ($existing->append_fingerprint !== $fingerprint || $existing->event_fingerprint !== $append->event->fingerprint()) {
                    throw new HistoricalAppendConflict("Historical event [{$eventId}] was reused with different content.");
                }

                return;
            }
            $appendId = $this->connection->table('funes_graph_appends')->insertGetId(['event_id' => $eventId, 'event_fingerprint' => $append->event->fingerprint(), 'append_fingerprint' => $fingerprint, 'authorization_context' => json_encode($append->authorization->authorizationContext(), JSON_THROW_ON_ERROR), 'appended_at' => new DateTimeImmutable]);
            $entities = [];
            foreach ($append->entities as $draft) {
                if (isset($entities[$draft->key])) {
                    throw new HistoricalAppendConflict("Duplicate entity key [{$draft->key}].");
                } $entities[$draft->key] = $this->identities->resolve($draft->identity);
                $this->link($appendId, 'entity', $entities[$draft->key]->reference->id);
            }
            foreach ($append->identifiers as $alias) {
                $entity = $entities[$alias->entityKey] ?? throw new HistoricalAppendConflict("Unknown alias entity [{$alias->entityKey}].");
                $entities[$alias->entityKey] = $this->identities->attach($entity->reference, $alias->identity);
                $this->link($appendId, 'identity', hash('sha256', $alias->identity->sourceReference."\0".$alias->identity->externalIdentifier));
            }
            foreach ($append->relations as $relation) {
                $subject = $entities[$relation->subjectKey] ?? throw new HistoricalAppendConflict("Unknown relation subject [{$relation->subjectKey}].");
                $object = $entities[$relation->objectKey] ?? throw new HistoricalAppendConflict("Unknown relation object [{$relation->objectKey}].");
                $fact = $this->relationFingerprint($relation, $subject, $object);
                $relationId = (string) Str::ulid();
                $inserted = $this->connection->table('funes_entity_relations')->insertOrIgnore(['id' => $relationId, 'subject_entity_id' => substr($subject->reference->id, 6), 'predicate' => $relation->predicate, 'object_entity_id' => substr($object->reference->id, 6), 'assertion_type' => $relation->assertionType->value, 'source_reference' => $relation->sourceReference, 'confidence' => $relation->confidence, 'occurred_at' => $relation->occurredAt, 'fingerprint' => $fact, 'recorded_at' => new DateTimeImmutable]);
                if (! $inserted) {
                    $relationId = (string) $this->connection->table('funes_entity_relations')->where('fingerprint', $fact)->value('id');
                }
                foreach (array_unique($relation->evidenceReferences) as $evidence) {
                    $this->connection->table('funes_entity_relation_evidence')->insertOrIgnore(['relation_id' => $relationId, 'evidence_reference' => $evidence]);
                }
                $this->link($appendId, 'relation', $fact);
            }
        }, 3);
    }

    private function appendFingerprint(HistoricalAppend $a): string
    {
        return $this->fingerprint(['authorization' => $a->authorization->toArray(), 'entities' => array_map(fn ($v) => [$v->key, $v->identity->kind->value, $v->identity->sourceReference, $v->identity->externalIdentifier, $v->identity->provenanceId], $a->entities), 'identifiers' => array_map(fn ($v) => [$v->entityKey, $v->identity->kind->value, $v->identity->sourceReference, $v->identity->externalIdentifier, $v->identity->provenanceId], $a->identifiers), 'relations' => array_map(fn ($v) => [$v->subjectKey, $v->predicate, $v->objectKey, $v->sourceReference, $v->assertionType->value, $v->evidenceReferences, $v->confidence, $v->occurredAt], $a->relations)]);
    }

    private function relationFingerprint(HistoricalRelationDraft $r, StableEntity $s, StableEntity $o): string
    {
        $e = $r->evidenceReferences;
        sort($e, SORT_STRING);

        return $this->fingerprint([$s->reference->id, $r->predicate, $o->reference->id, $r->sourceReference, $r->assertionType->value, $e, $r->confidence, $r->occurredAt]);
    }

    /** @param array<mixed, mixed> $value */
    private function fingerprint(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function link(int $appendId, string $kind, string $reference): void
    {
        $this->connection->table('funes_graph_append_facts')->insertOrIgnore(['append_id' => $appendId, 'fact_kind' => $kind, 'fact_reference' => $reference]);
    }
}
