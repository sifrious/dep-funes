<?php

declare(strict_types=1);

use Sifrious\Funes\Event\EventEnvelope;

it('rejects unsupported contracts and incomplete event identity', function (): void {
    $fixture = json_decode(
        file_get_contents(__DIR__.'/../Fixtures/Events/aleph-observation-ingested.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect(fn () => EventEnvelope::fromArray([...$fixture, 'contract_version' => 2]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => EventEnvelope::fromArray([...$fixture, 'id' => '']))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => EventEnvelope::fromArray([...$fixture, 'subjects' => []]))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects impossible temporal order', function (): void {
    $fixture = json_decode(
        file_get_contents(__DIR__.'/../Fixtures/Events/aleph-observation-ingested.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect(fn () => EventEnvelope::fromArray([
        ...$fixture,
        'observed_at' => '2026-08-28T09:59:59.000000+00:00',
    ]))->toThrow(InvalidArgumentException::class);
});
