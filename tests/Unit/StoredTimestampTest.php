<?php

declare(strict_types=1);

use Sifrious\Funes\Time\StoredTimestamp;

it('writes a moment as UTC with microsecond precision', function (): void {
    expect(StoredTimestamp::format(new DateTimeImmutable('2026-08-30T12:00:00.123456+00:00')))
        ->toBe('2026-08-30 12:00:00.123456');
});

it('converts a reported offset to UTC rather than storing its wall clock', function (): void {
    expect(StoredTimestamp::format(new DateTimeImmutable('2026-08-30T14:00:00.123456+02:00')))
        ->toBe('2026-08-30 12:00:00.123456');
});

it('gives one instant one stored value whatever offset reported it', function (): void {
    expect(StoredTimestamp::format(new DateTimeImmutable('2026-08-30T14:00:00+02:00')))
        ->toBe(StoredTimestamp::format(new DateTimeImmutable('2026-08-30T12:00:00+00:00')));
});

it('sorts lexicographically in instant order across offsets', function (): void {
    $earlier = StoredTimestamp::format(new DateTimeImmutable('2026-08-30T15:00:00+02:00'));
    $later = StoredTimestamp::format(new DateTimeImmutable('2026-08-30T14:00:00+00:00'));

    // The earlier instant has the later wall clock, so a naive string would misorder.
    expect(strcmp((string) $earlier, (string) $later))->toBeLessThan(0);
});

it('round-trips a moment without losing the instant or its microseconds', function (): void {
    $moment = new DateTimeImmutable('2026-08-30T14:00:00.123456+02:00');
    $restored = StoredTimestamp::require(StoredTimestamp::format($moment));

    expect($restored->getTimestamp())->toBe($moment->getTimestamp())
        ->and($restored->format('u'))->toBe('123456')
        ->and($restored == $moment)->toBeTrue();
});

it('passes null through in both directions', function (): void {
    expect(StoredTimestamp::format(null))->toBeNull()
        ->and(StoredTimestamp::parse(null))->toBeNull()
        ->and(StoredTimestamp::normalize(null))->toBeNull();
});

it('normalizes a caller supplied string into the column format', function (): void {
    expect(StoredTimestamp::normalize('2026-08-30T14:00:00.123456+02:00'))
        ->toBe('2026-08-30 12:00:00.123456');
});

it('refuses a string that is not a usable moment', function (): void {
    StoredTimestamp::normalize('not a timestamp');
})->throws(InvalidArgumentException::class, 'not a usable historical timestamp');

it('reads a stored value back as UTC rather than the ambient timezone', function (): void {
    $default = date_default_timezone_get();
    date_default_timezone_set('America/New_York');

    try {
        expect(StoredTimestamp::require('2026-08-30 12:00:00.123456')->getOffset())->toBe(0);
    } finally {
        date_default_timezone_set($default);
    }
});

it('requires a value where the schema guarantees one', function (): void {
    StoredTimestamp::require(null);
})->throws(InvalidArgumentException::class, 'required stored timestamp was null');

it('leaves no store binding a date object straight to a column', function (): void {
    foreach (glob(dirname(__DIR__, 2).'/src/*/Sql*.php') as $file) {
        $source = file_get_contents($file);

        // A bare date value in an insert or update lets the driver format it, which
        // truncates to whole seconds and drops the reported offset.
        expect($source)->not->toMatch("/'\\w+_at' => \\\$(?!\\w*\\()[^,\\]]*,/", basename($file).' binds a raw value to a timestamp column.')
            ->and($source)->not->toMatch("/'\\w+_at' => new DateTimeImmutable[,\\]]/", basename($file).' binds a raw date to a timestamp column.');
    }
});
