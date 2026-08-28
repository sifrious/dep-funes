<?php

declare(strict_types=1);

namespace Sifrious\Funes\Acceptance;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Sifrious\Funes\Persistence\ObservationStore;
use Throwable;

final readonly class SqlAcceptanceGateway implements AcceptanceGateway
{
    public function __construct(
        private ConnectionInterface $connection,
        private ObservationStore $observations,
    ) {}

    public function accept(Submission $submission): AcceptanceResult
    {
        $errors = $this->validate($submission);

        if ($errors !== []) {
            return AcceptanceResult::rejected($submission->idempotencyKey, $errors);
        }

        return $this->connection->transaction(function () use ($submission): AcceptanceResult {
            $reserved = $this->reserve($submission->idempotencyKey);

            if (! $reserved) {
                return $this->resolveExisting($submission->idempotencyKey);
            }

            $draft = $submission->occurredAt === null
                ? $submission->draft
                : $submission->draft->withOccurredAt($submission->occurredAt);
            $accepted = $this->observations->accept($draft);
            $now = new DateTimeImmutable;

            $this->connection->table('funes_idempotency_keys')
                ->where('key', $submission->idempotencyKey)
                ->update([
                    'accepted_type' => 'observation',
                    'accepted_id' => $accepted->observation->id,
                    'payload_hash' => $accepted->observation->payloadHash,
                    'accepted_at' => $now,
                ]);

            if ($submission->occurredAt !== null) {
                $this->connection->table('funes_observations')
                    ->where('id', $accepted->observation->id)
                    ->update(['occurred_at' => $submission->occurredAt]);
            }

            $this->emit($accepted->observation->id, $submission, $now);

            return AcceptanceResult::accepted(
                $submission->idempotencyKey,
                $accepted->observation->id,
                $accepted->disposition,
                $accepted->observation,
            );
        });
    }

    public function acceptBatch(array $submissions): array
    {
        $results = [];

        foreach ($submissions as $submission) {
            try {
                $results[] = $this->accept($submission);
            } catch (Throwable $failure) {
                $results[] = AcceptanceResult::rejected(
                    $submission->idempotencyKey,
                    [$failure::class.': '.$failure->getMessage()],
                );
            }
        }

        return $results;
    }

    private function reserve(string $key): bool
    {
        return $this->connection->table('funes_idempotency_keys')->insertOrIgnore([
            'key' => $key,
            'reserved_at' => new DateTimeImmutable,
        ]) === 1;
    }

    private function resolveExisting(string $key): AcceptanceResult
    {
        $row = $this->connection->table('funes_idempotency_keys')->where('key', $key)->first();

        if ($row === null || $row->accepted_id === null) {
            return AcceptanceResult::inFlight($key);
        }

        return AcceptanceResult::replayed(
            $key,
            (string) $row->accepted_type,
            (string) $row->accepted_id,
            $this->observations->get((string) $row->accepted_id),
        );
    }

    private function emit(string $observationId, Submission $submission, DateTimeImmutable $now): void
    {
        $this->connection->table('funes_outbox_messages')->insert([
            'id' => (string) Str::ulid(),
            'type' => 'observation.accepted',
            'accepted_type' => 'observation',
            'accepted_id' => $observationId,
            'payload' => json_encode([
                'idempotency_key' => $submission->idempotencyKey,
                'source' => $submission->draft->sourceReference,
                'resource' => $submission->draft->resourceReference,
            ], JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);
    }

    /**
     * @return list<string>
     */
    private function validate(Submission $submission): array
    {
        $errors = [];
        $draft = $submission->draft;

        if (trim($draft->sourceReference) === '') {
            $errors[] = 'source reference must not be empty';
        }

        if (trim($draft->resourceReference) === '') {
            $errors[] = 'resource reference must not be empty';
        }

        if ($submission->occurredAt !== null && $submission->occurredAt > $draft->observedAt) {
            $errors[] = 'occurred_at cannot be later than observed_at';
        }

        return $errors;
    }
}
