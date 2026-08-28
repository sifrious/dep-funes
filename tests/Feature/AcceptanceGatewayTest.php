<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Sifrious\Funes\Acceptance\AcceptanceGateway;
use Sifrious\Funes\Acceptance\AcceptanceOutcome;
use Sifrious\Funes\Acceptance\Submission;
use Sifrious\Funes\Value\ObservationDraft;

uses(RefreshDatabase::class);

function draft(string $resource = 'res-1', string $payload = 'body'): ObservationDraft
{
    return new ObservationDraft(
        sourceReference: 'src-1',
        sourceName: 'Source One',
        resourceReference: $resource,
        observedAt: new DateTimeImmutable('2026-08-27T10:00:00+00:00'),
        payload: $payload,
    );
}

function gateway(): AcceptanceGateway
{
    return app(AcceptanceGateway::class);
}

it('accepts a valid submission and returns an authoritative id', function (): void {
    $result = gateway()->accept(new Submission('key-1', draft()));

    expect($result->outcome)->toBe(AcceptanceOutcome::Accepted)
        ->and($result->acceptedType)->toBe('observation')
        ->and($result->acceptedId)->not->toBeNull()
        ->and($result->observation->id)->toBe($result->acceptedId);
});

it('returns the same accepted id when the key is replayed', function (): void {
    $first = gateway()->accept(new Submission('key-1', draft()));
    $second = gateway()->accept(new Submission('key-1', draft()));

    expect($second->outcome)->toBe(AcceptanceOutcome::Replayed)
        ->and($second->acceptedId)->toBe($first->acceptedId)
        ->and(DB::table('funes_observations')->count())->toBe(1);
});

it('produces one accepted fact when replayed ten times', function (): void {
    for ($i = 0; $i < 10; $i++) {
        $results[] = gateway()->accept(new Submission('key-1', draft()));
    }

    $ids = array_unique(array_map(static fn ($r): string => $r->acceptedId, $results));

    expect($ids)->toHaveCount(1)
        ->and(DB::table('funes_observations')->count())->toBe(1)
        ->and(DB::table('funes_idempotency_keys')->count())->toBe(1);
});

it('rejects an invalid submission without reserving a key', function (): void {
    $result = gateway()->accept(new Submission('key-bad', draft(), new DateTimeImmutable('2030-01-01T00:00:00+00:00')));

    expect($result->outcome)->toBe(AcceptanceOutcome::Rejected)
        ->and($result->errors)->toContain('occurred_at cannot be later than observed_at')
        ->and($result->acceptedId)->toBeNull()
        ->and(DB::table('funes_idempotency_keys')->count())->toBe(0);
});

it('refuses a submission without an idempotency key', function (): void {
    new Submission('  ', draft());
})->throws(InvalidArgumentException::class);

it('keeps occurred, observed and accepted times distinct', function (): void {
    $result = gateway()->accept(new Submission(
        'key-1',
        draft(),
        new DateTimeImmutable('2026-08-27T08:00:00+00:00'),
    ));

    $row = DB::table('funes_observations')->where('id', $result->acceptedId)->first();

    expect(substr((string) $row->occurred_at, 0, 10))->toBe('2026-08-27')
        ->and($row->occurred_at)->not->toBe($row->observed_at)
        ->and($row->observed_at)->not->toBe($row->ingested_at);
});

it('writes an outbox message in the same transaction as acceptance', function (): void {
    $result = gateway()->accept(new Submission('key-1', draft()));

    $message = DB::table('funes_outbox_messages')->first();

    expect($message->type)->toBe('observation.accepted')
        ->and($message->accepted_id)->toBe($result->acceptedId)
        ->and($message->published_at)->toBeNull();
});

it('does not emit a second outbox message for a replay', function (): void {
    gateway()->accept(new Submission('key-1', draft()));
    gateway()->accept(new Submission('key-1', draft()));

    expect(DB::table('funes_outbox_messages')->count())->toBe(1);
});

it('accepts a batch and reports each result independently', function (): void {
    $results = gateway()->acceptBatch([
        new Submission('key-a', draft('res-a')),
        new Submission('key-b', draft('res-b')),
        new Submission('key-a', draft('res-a')),
    ]);

    expect($results)->toHaveCount(3)
        ->and($results[0]->outcome)->toBe(AcceptanceOutcome::Accepted)
        ->and($results[1]->outcome)->toBe(AcceptanceOutcome::Accepted)
        ->and($results[2]->outcome)->toBe(AcceptanceOutcome::Replayed)
        ->and(DB::table('funes_observations')->count())->toBe(2);
});

it('treats a reserved but unfinished key as in flight rather than accepted', function (): void {
    DB::table('funes_idempotency_keys')->insert([
        'key' => 'key-stuck',
        'reserved_at' => new DateTimeImmutable('2026-08-27T09:00:00+00:00'),
    ]);

    $result = gateway()->accept(new Submission('key-stuck', draft()));

    expect($result->outcome)->toBe(AcceptanceOutcome::InFlight)
        ->and($result->outcome->isAuthoritative())->toBeFalse()
        ->and(DB::table('funes_observations')->count())->toBe(0);
});
