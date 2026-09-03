<?php

declare(strict_types=1);

namespace Sifrious\Funes\Time;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

/**
 * The single authority for how a moment is written to and read from a Funes column.
 *
 * A driver's own date binding formats a value in whatever timezone it arrives in and
 * to whole seconds. Both losses matter here. Truncating microseconds discards ordering
 * this package promises to preserve, and dropping the offset is worse than imprecise:
 * a source reporting noon at +02:00 and one reporting noon at UTC are the same instant
 * two hours apart, and stored as bare wall-clock text they become indistinguishable
 * from two different instants — silently corrupting comparison, ordering, and any
 * point-in-time reconstruction built on them.
 *
 * So a stored moment is normalized to UTC and written at microsecond precision. UTC
 * makes the text lexicographically comparable no matter what offset a source reported,
 * which is what lets an index range-scan a timeline. The offset a source actually used
 * is not thrown away: it survives in the canonical document or value object that
 * retrieval hydrates from. These columns exist to filter and order, not to be the
 * record of what a source said.
 */
final readonly class StoredTimestamp
{
    /** The column format: UTC, microsecond precision, lexicographically sortable. */
    public const FORMAT = 'Y-m-d H:i:s.u';

    public static function format(?DateTimeInterface $moment): ?string
    {
        if ($moment === null) {
            return null;
        }

        return DateTimeImmutable::createFromInterface($moment)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(self::FORMAT);
    }

    public static function parse(mixed $stored): ?DateTimeImmutable
    {
        if ($stored === null) {
            return null;
        }

        return new DateTimeImmutable((string) $stored, new DateTimeZone('UTC'));
    }

    /**
     * Normalize a caller-supplied timestamp string into the column format.
     *
     * Some drafts carry a moment as text rather than a date object. Stored verbatim,
     * such a value inherits every problem this class exists to solve: two offsets for
     * one instant sort as two instants. An unparseable value fails here rather than
     * becoming a column that cannot be compared.
     */
    public static function normalize(?string $moment): ?string
    {
        if ($moment === null) {
            return null;
        }

        try {
            return self::format(new DateTimeImmutable($moment));
        } catch (Exception) {
            throw new InvalidArgumentException("[{$moment}] is not a usable historical timestamp.");
        }
    }

    /** Read a column that the schema guarantees is present. */
    public static function require(mixed $stored): DateTimeImmutable
    {
        return self::parse($stored) ?? throw new InvalidArgumentException('A required stored timestamp was null.');
    }
}
