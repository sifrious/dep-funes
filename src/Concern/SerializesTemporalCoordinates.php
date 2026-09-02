<?php

declare(strict_types=1);

namespace Sifrious\Funes\Concern;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Shared mechanics for records implementing {@see HasTemporalCoordinates}.
 *
 * This trait holds no state. Every historical record owns its own temporal
 * properties; what repeats across them is the chronology rule and the wire format,
 * so only those live here. A record that formats times its own way would produce
 * documents that no longer compare or sort against its siblings.
 */
trait SerializesTemporalCoordinates
{
    /**
     * Occurrence, when known, precedes observation, which precedes recording.
     *
     * @param  string  $subject  How the record names itself in the failure message.
     */
    protected static function requireChronology(
        ?DateTimeImmutable $occurredAt,
        DateTimeImmutable $observedAt,
        DateTimeImmutable $recordedAt,
        string $subject,
    ): void {
        if ($occurredAt !== null && $occurredAt > $observedAt) {
            throw new InvalidArgumentException("A {$subject} cannot be observed before the fact it reports occurred.");
        }

        if ($observedAt > $recordedAt) {
            throw new InvalidArgumentException("A {$subject} cannot be recorded before it was observed.");
        }
    }

    /** The canonical wire format: microsecond precision with an explicit offset. */
    protected static function formatTime(DateTimeImmutable $time): string
    {
        return $time->format('Y-m-d\TH:i:s.uP');
    }

    protected static function parseTime(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time);
    }
}
