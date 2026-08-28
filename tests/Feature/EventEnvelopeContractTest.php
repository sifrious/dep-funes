<?php

declare(strict_types=1);

use Sifrious\Funes\Event\DeliveryAttempt;
use Sifrious\Funes\Event\DeliveryStatus;
use Sifrious\Funes\Event\EventEnvelope;
use Sifrious\Funes\Reference\CrossPackageReference;

function eventFixture(string $name): array
{
    return json_decode(
        file_get_contents(__DIR__."/../Fixtures/Events/{$name}.json"),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}

it('round trips Aleph Titan and Logres events through one immutable envelope', function (string $fixture): void {
    $serialized = eventFixture($fixture);
    $event = EventEnvelope::fromArray($serialized);
    $queued = json_decode(json_encode($event, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

    expect($queued)->toBe($serialized)
        ->and(EventEnvelope::fromArray($queued)->toArray())->toBe($serialized)
        ->and($event->idempotencyKey())->toBe($serialized['id'])
        ->and($event->fingerprint())->toBe(EventEnvelope::fromArray($queued)->fingerprint());
})->with([
    'Aleph observation ingestion' => 'aleph-observation-ingested',
    'Titan work lifecycle' => 'titan-work-started',
    'Logres runtime execution' => 'logres-execution-completed',
]);

it('makes duplicate redelivery idempotent and conflicting event-id reuse explicit at the consumer boundary', function (): void {
    $accepted = [];
    $effects = 0;
    $accept = function (EventEnvelope $event) use (&$accepted, &$effects): string {
        $knownFingerprint = $accepted[$event->idempotencyKey()] ?? null;

        if ($knownFingerprint === $event->fingerprint()) {
            return 'replayed';
        }

        if ($knownFingerprint !== null) {
            throw new RuntimeException('event-id-conflict');
        }

        $accepted[$event->idempotencyKey()] = $event->fingerprint();
        $effects++;

        return 'accepted';
    };
    $event = EventEnvelope::fromArray(eventFixture('aleph-observation-ingested'));

    expect($accept($event))->toBe('accepted')
        ->and($accept(EventEnvelope::fromArray($event->toArray())))->toBe('replayed')
        ->and($effects)->toBe(1);

    expect(fn () => $accept(EventEnvelope::fromArray([
        ...$event->toArray(),
        'payload' => ['resource' => 'github:issue_43', 'content_hash' => 'sha256:changed'],
    ])))->toThrow(RuntimeException::class, 'event-id-conflict');
});

it('separates retry and dead-letter attempts from the original event fact', function (): void {
    $event = EventEnvelope::fromArray(eventFixture('logres-execution-completed'));
    $original = $event->toArray();
    $retry = new DeliveryAttempt(
        'delivery_01',
        $event->id,
        $event->fingerprint(),
        'sifrious/funes',
        1,
        new DateTimeImmutable('2026-08-28T10:04:00+00:00'),
        DeliveryStatus::RetryableFailure,
        'storage-unavailable',
        new DateTimeImmutable('2026-08-28T10:05:00+00:00'),
    );
    $deadLettered = new DeliveryAttempt(
        'delivery_02',
        $event->id,
        $event->fingerprint(),
        'sifrious/funes',
        2,
        new DateTimeImmutable('2026-08-28T10:05:00+00:00'),
        DeliveryStatus::DeadLettered,
        'schema-rejected',
        deadLetter: new CrossPackageReference('sifrious/logres', 'dead-letter', 'dead_01'),
    );

    expect(DeliveryAttempt::fromArray($retry->toArray())->toArray())->toBe($retry->toArray())
        ->and(DeliveryAttempt::fromArray($deadLettered->toArray())->toArray())->toBe($deadLettered->toArray())
        ->and($event->toArray())->toBe($original)
        ->and($retry->eventId)->toBe($deadLettered->eventId)
        ->and($retry->eventFingerprint)->toBe($deadLettered->eventFingerprint);
});

it('provides only explicit stream ordering and reconstructs causation within a correlation', function (): void {
    $aleph = EventEnvelope::fromArray(eventFixture('aleph-observation-ingested'));
    $titan = EventEnvelope::fromArray(eventFixture('titan-work-started'));
    $logres = EventEnvelope::fromArray(eventFixture('logres-execution-completed'));

    expect($aleph->streamPosition?->sequence)->toBe(12)
        ->and($titan->streamPosition?->sequence)->toBe(7)
        ->and($aleph->streamPosition?->stream->equals($titan->streamPosition?->stream))->toBeFalse()
        ->and($logres->streamPosition)->toBeNull()
        ->and($titan->causationId)->toBe($aleph->id)
        ->and($logres->causationId)->toBe($titan->id)
        ->and(array_unique([$aleph->correlationId, $titan->correlationId, $logres->correlationId]))->toBe(['sync_01']);
});
