<?php

declare(strict_types=1);

namespace Sifrious\Funes\TwinkleHistory;

use InvalidArgumentException;
use Sifrious\Funes\Event\EventEnvelope;
use Sifrious\Funes\Reference\CrossPackageReference;

final class InMemoryTwinkleHistory implements TwinkleHistory
{
    private const TYPES = ['twinkle.captured', 'twinkle.revised', 'twinkle.deferred', 'twinkle.reactivated', 'twinkle.dismissed', 'twinkle.promoted', 'twinkle.merged'];

    /** @var array<string, EventEnvelope> */
    private array $events = [];

    public function accept(EventEnvelope $event): bool
    {
        if ($event->producer !== 'sifrious/elwin' || ! in_array($event->type, self::TYPES, true)) {
            throw new InvalidArgumentException('Twinkle history accepts only supported Elwin lifecycle events.');
        }

        if (! $this->hasSubject($event, 'sifrious/elwin', ['twinkle'])) {
            throw new InvalidArgumentException('A Twinkle lifecycle event must identify its Elwin Twinkle subject.');
        }

        if (! isset($event->payload['twinkle_version']) || ! is_int($event->payload['twinkle_version']) || $event->payload['twinkle_version'] < 1) {
            throw new InvalidArgumentException('A Twinkle lifecycle event requires a positive Twinkle version.');
        }

        if ($event->type === 'twinkle.promoted' && ! $this->hasSubject($event, 'sifrious/titan', ['plan', 'work-kit'])) {
            throw new InvalidArgumentException('A promotion event must retain the Titan work reference.');
        }

        if ($event->type === 'twinkle.merged' && $this->twinkleSubjectCount($event) < 2) {
            throw new InvalidArgumentException('A merge event must retain source and target Twinkle identities.');
        }

        $known = $this->events[$event->id] ?? null;
        if ($known?->fingerprint() === $event->fingerprint()) {
            return false;
        }

        if ($known !== null) {
            throw new ConflictingTwinkleEvent('The event id already identifies different historical evidence.');
        }

        $this->events[$event->id] = $event;

        return true;
    }

    public function forTwinkle(CrossPackageReference $twinkle): array
    {
        $events = array_values(array_filter($this->events, fn (EventEnvelope $event): bool => array_any(
            $event->subjects,
            fn (CrossPackageReference $subject): bool => $subject->equals($twinkle),
        )));

        usort($events, fn (EventEnvelope $a, EventEnvelope $b): int => [$a->occurredAt, $a->recordedAt, $a->id] <=> [$b->occurredAt, $b->recordedAt, $b->id]);

        return $events;
    }

    private function twinkleSubjectCount(EventEnvelope $event): int
    {
        return count(array_filter(
            $event->subjects,
            fn (CrossPackageReference $subject): bool => $subject->owner === 'sifrious/elwin' && $subject->type === 'twinkle',
        ));
    }

    /**
     * @param  list<string>  $types
     */
    private function hasSubject(EventEnvelope $event, string $owner, array $types): bool
    {
        return array_any(
            $event->subjects,
            fn (CrossPackageReference $subject): bool => $subject->owner === $owner && in_array($subject->type, $types, true),
        );
    }
}
