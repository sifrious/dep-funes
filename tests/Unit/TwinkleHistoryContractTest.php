<?php

declare(strict_types=1);

use Sifrious\Funes\Event\EventEnvelope;
use Sifrious\Funes\Reference\CrossPackageReference;
use Sifrious\Funes\TwinkleHistory\ConflictingTwinkleEvent;
use Sifrious\Funes\TwinkleHistory\InMemoryTwinkleHistory;

function twinkleRef(string $id, string $version): CrossPackageReference
{
    return new CrossPackageReference('sifrious/elwin', 'twinkle', $id, $version);
}

/**
 * @param  list<CrossPackageReference>  $subjects
 */
function twinkleEvent(string $id, string $type, int $version, array $subjects, string $time): EventEnvelope
{
    $at = new DateTimeImmutable($time);

    return new EventEnvelope(
        $id,
        $type,
        'sifrious/elwin',
        '1',
        $at,
        $at,
        $at,
        $subjects,
        null,
        'twinkle-flow:1',
        [new CrossPackageReference('sifrious/elwin', 'conversation', 'chat:1')],
        null,
        ['twinkle_version' => $version, 'actor' => 'user:mary', 'rationale' => 'chosen by user'],
    );
}

it('preserves ordered lifecycle evidence and makes exact redelivery idempotent', function (): void {
    $twinkle = twinkleRef('twinkle:1', '1');
    $history = new InMemoryTwinkleHistory;
    $captured = twinkleEvent('event:1', 'twinkle.captured', 1, [$twinkle], '2026-08-29T12:00:00Z');
    $deferred = twinkleEvent('event:2', 'twinkle.deferred', 2, [$twinkle], '2026-08-30T12:00:00Z');

    expect($history->accept($deferred))->toBeTrue()
        ->and($history->accept($captured))->toBeTrue()
        ->and($history->accept(EventEnvelope::fromArray($captured->toArray())))->toBeFalse()
        ->and(array_map(fn ($event) => $event->type, $history->forTwinkle($twinkle)))->toBe(['twinkle.captured', 'twinkle.deferred']);
});

it('retains both Elwin and Titan references for promotion history', function (): void {
    $twinkle = twinkleRef('twinkle:1', '3');
    $plan = new CrossPackageReference('sifrious/titan', 'plan', 'plan:7');
    $history = new InMemoryTwinkleHistory;
    $history->accept(twinkleEvent('event:3', 'twinkle.promoted', 3, [$twinkle, $plan], '2026-09-01T12:00:00Z'));

    expect($history->forTwinkle($twinkle)[0]->subjects[1]->equals($plan))->toBeTrue();
});

it('retains source and target identities for merge history', function (): void {
    $source = twinkleRef('twinkle:1', '2');
    $target = twinkleRef('twinkle:2', '4');
    $history = new InMemoryTwinkleHistory;
    $history->accept(twinkleEvent('event:4', 'twinkle.merged', 2, [$source, $target], '2026-09-02T12:00:00Z'));

    expect($history->forTwinkle($source))->toHaveCount(1)->and($history->forTwinkle($target))->toHaveCount(1);
});

it('rejects conflicting event id reuse', function (): void {
    $history = new InMemoryTwinkleHistory;
    $history->accept(twinkleEvent('event:5', 'twinkle.captured', 1, [twinkleRef('twinkle:1', '1')], '2026-08-29T12:00:00Z'));

    expect(fn () => $history->accept(
        twinkleEvent('event:5', 'twinkle.revised', 2, [twinkleRef('twinkle:1', '2')], '2026-08-30T12:00:00Z'),
    ))->toThrow(ConflictingTwinkleEvent::class);
});
