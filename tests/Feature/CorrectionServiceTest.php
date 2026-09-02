<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Sifrious\Funes\Correction\CorrectionDraft;
use Sifrious\Funes\Correction\CorrectionService;
use Sifrious\Funes\Persistence\ObservationStore;
use Sifrious\Funes\Relationship\HistoricalRelationshipType;
use Sifrious\Funes\Value\ObservationDraft;

uses(RefreshDatabase::class);

function originalObservationDraft(string $payload = '<html>first</html>'): ObservationDraft
{
    return new ObservationDraft(
        sourceReference: 'website:example',
        sourceName: 'Example website',
        resourceReference: 'https://example.test/articles/one',
        producerReference: 'aleph:connector/web',
        producerName: 'Aleph web connector',
        ingestionRunReference: 'aleph:run/2026-08-26T12:00:00Z',
        observedAt: new DateTimeImmutable('2026-08-26T12:00:00+00:00'),
        payload: $payload,
        occurredAt: new DateTimeImmutable('2026-08-26T11:55:00+00:00'),
        transformationLineage: ['aleph:fetch/1'],
        contentType: 'text/html',
    );
}

function correctionDraft(
    string $idempotencyKey,
    string $payload,
    string $observedAt,
    HistoricalRelationshipType $relationType = HistoricalRelationshipType::Corrects,
): CorrectionDraft {
    return new CorrectionDraft(
        idempotencyKey: $idempotencyKey,
        payload: $payload,
        producerReference: 'aleph:correction/web',
        producerName: 'Aleph correction connector',
        ingestionRunReference: 'aleph:run/correction',
        observedAt: new DateTimeImmutable($observedAt),
        relationType: $relationType,
        occurredAt: new DateTimeImmutable('2026-08-27T11:55:00+00:00'),
        transformationLineage: ['aleph:normalize/correction'],
        contentType: 'text/html',
    );
}

it('appends a correction relationship and preserves the original observation', function (): void {
    $store = app(ObservationStore::class);
    $service = app(CorrectionService::class);
    $original = $store->accept(originalObservationDraft())->observation;

    $result = $service->apply($original->id, correctionDraft(
        'correction-key-1',
        '<html>corrected</html>',
        '2026-08-27T12:00:00+00:00',
    ));
    $corrected = $store->get((string) $result->acceptedId);
    $originalAfter = $store->get($original->id);

    expect($corrected)->not->toBeNull()
        ->and($corrected?->id)->not->toBe($original->id)
        ->and($corrected?->related(HistoricalRelationshipType::Corrects))->toHaveCount(1)
        ->and($corrected?->related(HistoricalRelationshipType::Corrects)[0]->target->equals($original->reference()))->toBeTrue()
        ->and($corrected?->relationships[0]->provenanceIds)->toBe([$corrected?->provenance[0]->id])
        ->and($originalAfter?->payload)->toBe('<html>first</html>')
        ->and($originalAfter?->sourceReference)->toBe('website:example')
        ->and($originalAfter?->provenance)->toHaveCount(1)
        ->and($originalAfter?->provenance[0]->source->resourceReference)->toBe('https://example.test/articles/one')
        ->and(DB::table('funes_observations')->count())->toBe(2);
});

it('is idempotent when the same correction identity is retried', function (): void {
    $store = app(ObservationStore::class);
    $service = app(CorrectionService::class);
    $original = $store->accept(originalObservationDraft())->observation;
    $draft = correctionDraft(
        'correction-key-1',
        '<html>corrected</html>',
        '2026-08-27T12:00:00+00:00',
    );

    $first = $service->apply($original->id, $draft);
    $second = $service->apply($original->id, $draft);

    expect($second->acceptedId)->toBe($first->acceptedId)
        ->and(DB::table('funes_observations')->count())->toBe(2)
        ->and(DB::table('funes_historical_relationships')->count())->toBe(1)
        ->and(DB::table('funes_historical_relationship_provenance')->count())->toBe(1);
});

it('records a second distinct correction as a new immutable version', function (): void {
    $store = app(ObservationStore::class);
    $service = app(CorrectionService::class);
    $original = $store->accept(originalObservationDraft())->observation;

    $first = $service->apply($original->id, correctionDraft(
        'correction-key-1',
        '<html>corrected once</html>',
        '2026-08-27T12:00:00+00:00',
    ));
    $second = $service->apply($original->id, correctionDraft(
        'correction-key-2',
        '<html>corrected twice</html>',
        '2026-08-28T12:00:00+00:00',
        HistoricalRelationshipType::Supersedes,
    ));

    $firstCorrection = $store->get((string) $first->acceptedId);
    $secondCorrection = $store->get((string) $second->acceptedId);
    $incoming = $store->relationshipsTo($original->reference());

    expect($firstCorrection)->not->toBeNull()
        ->and($secondCorrection)->not->toBeNull()
        ->and($secondCorrection?->id)->not->toBe($firstCorrection?->id)
        ->and($firstCorrection?->payload)->toBe('<html>corrected once</html>')
        ->and($secondCorrection?->payload)->toBe('<html>corrected twice</html>')
        ->and($secondCorrection?->related(HistoricalRelationshipType::Supersedes))->toHaveCount(1)
        ->and($incoming)->toHaveCount(2)
        ->and(DB::table('funes_observations')->count())->toBe(3);
});
