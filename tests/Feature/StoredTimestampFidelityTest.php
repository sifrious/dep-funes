<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Sifrious\Funes\Persistence\ObservationStore;
use Sifrious\Funes\Value\Discovery;
use Sifrious\Funes\Value\ObservationDraft;

uses(RefreshDatabase::class);

function momentDraft(string $resource, string $observedAt, string $occurredAt): ObservationDraft
{
    return new ObservationDraft(
        sourceReference: 'website:example',
        sourceName: 'Example website',
        resourceReference: $resource,
        producerReference: 'aleph:connector/web',
        producerName: 'Aleph web connector',
        ingestionRunReference: 'aleph:run/1',
        observedAt: new DateTimeImmutable($observedAt),
        payload: '<html>'.$resource.'</html>',
        occurredAt: new DateTimeImmutable($occurredAt),
        transformationLineage: ['aleph:fetch/1'],
        contentType: 'text/html',
        discoveries: [new Discovery('https://example.test/next', 'link')],
    );
}

it('preserves the microseconds a source reported', function (): void {
    $observedAt = new DateTimeImmutable('2026-08-26T12:00:00.123456+00:00');
    $accepted = app(ObservationStore::class)->accept(momentDraft(
        'https://example.test/one',
        '2026-08-26T12:00:00.123456+00:00',
        '2026-08-26T11:55:00.654321+00:00',
    ));

    $provenance = $accepted->observation->provenance[0];

    expect($accepted->observation->observedAt->format('u'))->toBe('123456')
        ->and($provenance->observedAt->format('u'))->toBe('123456')
        ->and($provenance->occurredAt?->format('u'))->toBe('654321')
        ->and($provenance->observedAt->getTimestamp())->toBe($observedAt->getTimestamp());
});

it('preserves the instant a source reported regardless of its offset', function (): void {
    $store = app(ObservationStore::class);

    $utc = $store->accept(momentDraft(
        'https://example.test/utc',
        '2026-08-26T12:00:00+00:00',
        '2026-08-26T11:00:00+00:00',
    ));
    $plusTwo = $store->accept(momentDraft(
        'https://example.test/plus-two',
        '2026-08-26T14:00:00+02:00',
        '2026-08-26T13:00:00+02:00',
    ));

    $first = $store->get($utc->observation->id)->provenance[0];
    $second = $store->get($plusTwo->observation->id)->provenance[0];

    // The same instant reported through two offsets must come back as one instant.
    expect($second->observedAt->getTimestamp())->toBe($first->observedAt->getTimestamp())
        ->and($second->occurredAt?->getTimestamp())->toBe($first->occurredAt?->getTimestamp());
});

it('orders a timeline by instant rather than by reported wall clock', function (): void {
    $store = app(ObservationStore::class);

    // Earlier instant, later wall clock: 15:00+02:00 is 13:00 UTC.
    $earlier = $store->accept(momentDraft(
        'https://example.test/earlier',
        '2026-08-26T15:00:00+02:00',
        '2026-08-26T10:00:00+00:00',
    ));
    // Later instant, earlier wall clock.
    $later = $store->accept(momentDraft(
        'https://example.test/later',
        '2026-08-26T14:00:00+00:00',
        '2026-08-26T10:00:00+00:00',
    ));

    $earliestObserved = $store->get($earlier->observation->id)->observedAt;
    $latestObserved = $store->get($later->observation->id)->observedAt;

    expect($earliestObserved->getTimestamp())->toBeLessThan($latestObserved->getTimestamp());
});
