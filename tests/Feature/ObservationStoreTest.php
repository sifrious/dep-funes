<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Sifrious\Funes\Persistence\ObservationConflict;
use Sifrious\Funes\Persistence\ObservationStore;
use Sifrious\Funes\Value\Discovery;
use Sifrious\Funes\Value\ExtractionDraft;
use Sifrious\Funes\Value\ObservationDisposition;
use Sifrious\Funes\Value\ObservationDraft;

uses(RefreshDatabase::class);

function observationDraft(
    string $payload = '<html>first</html>',
    string $observedAt = '2026-08-26T12:00:00+00:00',
): ObservationDraft {
    return new ObservationDraft(
        sourceReference: 'website:example',
        sourceName: 'Example website',
        resourceReference: 'https://example.test/articles/one',
        producerReference: 'aleph:connector/web',
        producerName: 'Aleph web connector',
        observedAt: new DateTimeImmutable($observedAt),
        payload: $payload,
        occurredAt: new DateTimeImmutable('2026-08-26T11:55:00+00:00'),
        transformationLineage: ['aleph:fetch/1'],
        contentType: 'text/html',
        metadata: ['status' => 200],
        discoveries: [new Discovery('https://example.test/articles/two', 'link')],
    );
}

it('atomically accepts and reads a recoverable observation by source reference', function (): void {
    $store = app(ObservationStore::class);
    $accepted = $store->accept(observationDraft());
    $found = $store->find('website:example', 'https://example.test/articles/one');

    expect($found)->not->toBeNull()
        ->and($accepted->disposition)->toBe(ObservationDisposition::First)
        ->and($found?->id)->toBe($accepted->observation->id)
        ->and($found?->payload)->toBe('<html>first</html>')
        ->and($found?->payloadHash)->toBe(hash('sha256', '<html>first</html>'))
        ->and($found?->contentType)->toBe('text/html')
        ->and($found?->ingestedAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($found?->resourceReference)->toBe('https://example.test/articles/one')
        ->and($found?->provenance)->toHaveCount(1)
        ->and($found?->provenance[0]->source->resourceReference)->toBe('https://example.test/articles/one')
        ->and($found?->provenance[0]->producer->reference)->toBe('aleph:connector/web')
        ->and($found?->provenance[0]->occurredAt?->format(DATE_ATOM))->toBe('2026-08-26T11:55:00+00:00')
        ->and($found?->provenance[0]->transformationLineage)->toBe(['aleph:fetch/1'])
        ->and($found?->discoveries)->toHaveCount(1)
        ->and($found?->discoveries[0]->canonicalReference)->toBe('https://example.test/articles/two')
        ->and(DB::table('funes_payloads')->value('byte_size'))->toBe(strlen('<html>first</html>'))
        ->and(DB::table('funes_observations')->value('content_type'))->toBe('text/html')
        ->and(DB::table('funes_observations')->value('ingested_at'))->not->toBeNull();
});

it('returns the original observation when acceptance is repeated', function (): void {
    $store = app(ObservationStore::class);

    $first = $store->accept(observationDraft());
    $second = $store->accept(observationDraft(observedAt: '2026-08-27T12:00:00+00:00'));

    expect($first->disposition)->toBe(ObservationDisposition::First)
        ->and($second->disposition)->toBe(ObservationDisposition::Unchanged)
        ->and($second->observation->id)->toBe($first->observation->id)
        ->and(DB::table('funes_observations')->count())->toBe(1)
        ->and(DB::table('funes_payloads')->count())->toBe(1)
        ->and(DB::table('funes_discoveries')->count())->toBe(1)
        ->and(DB::table('funes_observation_provenance')->count())->toBe(2);
});

it('does not duplicate an identical provenance assertion', function (): void {
    $store = app(ObservationStore::class);

    $store->accept(observationDraft());
    $replayed = $store->accept(observationDraft());

    expect($replayed->observation->provenance)->toHaveCount(1)
        ->and(DB::table('funes_observation_provenance')->count())->toBe(1);
});

it('rejects incomplete producer provenance', function (): void {
    new ObservationDraft(
        sourceReference: 'website:example',
        sourceName: 'Example website',
        resourceReference: 'https://example.test/articles/one',
        producerReference: '',
        producerName: 'Aleph web connector',
        observedAt: new DateTimeImmutable('2026-08-26T12:00:00+00:00'),
        payload: 'body',
    );
})->throws(InvalidArgumentException::class);

it('enforces producer provenance in storage', function (): void {
    $store = app(ObservationStore::class);
    $observation = $store->accept(observationDraft())->observation;
    $stored = DB::table('funes_observation_provenance')->first();

    DB::table('funes_observation_provenance')->insert([
        'id' => (string) Str::ulid(),
        'observation_id' => $observation->id,
        'source_id' => $stored->source_id,
        'resource_id' => $stored->resource_id,
        'producer_name' => 'Missing identity',
        'observed_at' => new DateTimeImmutable('2026-08-27T12:00:00+00:00'),
        'recorded_at' => new DateTimeImmutable('2026-08-27T12:01:00+00:00'),
        'transformation_lineage' => '[]',
        'fingerprint' => hash('sha256', 'missing-producer-reference'),
    ]);
})->throws(QueryException::class);

it('resolves a discovered resource back to its parent observation', function (): void {
    $store = app(ObservationStore::class);
    $accepted = $store->accept(observationDraft());
    $provenance = $store->discoveriesTo('website:example', 'https://example.test/articles/two');

    expect($provenance)->toHaveCount(1)
        ->and($provenance[0]->observationId)->toBe($accepted->observation->id)
        ->and($provenance[0]->parentResourceReference)->toBe('https://example.test/articles/one')
        ->and($provenance[0]->resourceReference)->toBe('https://example.test/articles/two')
        ->and($provenance[0]->relationship)->toBe('link');
});

it('creates a new immutable observation when the resource content changes', function (): void {
    $store = app(ObservationStore::class);
    $first = $store->accept(observationDraft());
    $second = $store->accept(observationDraft('<html>changed</html>', '2026-08-27T12:00:00+00:00'));

    expect($second->disposition)->toBe(ObservationDisposition::Changed)
        ->and($second->observation->id)->not->toBe($first->observation->id)
        ->and(DB::table('funes_observations')->count())->toBe(2)
        ->and($store->get($first->observation->id)?->payload)->toBe('<html>first</html>')
        ->and($store->get($second->observation->id)?->payload)->toBe('<html>changed</html>')
        ->and($store->find('website:example', 'https://example.test/articles/one')?->id)->toBe($second->observation->id);
});

it('records versioned extraction successes and failures without changing observations', function (): void {
    $store = app(ObservationStore::class);
    $observation = $store->accept(observationDraft())->observation;

    $success = $store->recordExtraction(new ExtractionDraft(
        $observation->id,
        'article',
        '1',
        ['title' => 'First'],
    ));
    $failure = $store->recordExtraction(new ExtractionDraft(
        $observation->id,
        'article',
        '2',
        failure: 'Unsupported document',
    ));

    expect($success->succeeded())->toBeTrue()
        ->and($success->result)->toBe(['title' => 'First'])
        ->and($failure->succeeded())->toBeFalse()
        ->and($failure->failure)->toBe('Unsupported document')
        ->and($store->find('website:example', 'https://example.test/articles/one')?->payload)->toBe('<html>first</html>');
});

it('makes repeated extraction recording idempotent and rejects conflicting reuse', function (): void {
    $store = app(ObservationStore::class);
    $observation = $store->accept(observationDraft())->observation;
    $draft = new ExtractionDraft($observation->id, 'article', '1', ['title' => 'First']);

    $first = $store->recordExtraction($draft);
    $second = $store->recordExtraction($draft);

    expect($second->id)->toBe($first->id)
        ->and(fn () => $store->recordExtraction(new ExtractionDraft(
            $observation->id,
            'article',
            '1',
            ['title' => 'Changed'],
        )))->toThrow(ObservationConflict::class);
});
