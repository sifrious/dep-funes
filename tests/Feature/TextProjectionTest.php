<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Sifrious\Funes\Persistence\ObservationStore;
use Sifrious\Funes\Text\TextProjection;
use Sifrious\Funes\Value\ObservationDraft;
use Sifrious\Funes\Value\TextDraft;

uses(RefreshDatabase::class);

function textObservation(string $text = 'Funes remembers the source.'): ObservationDraft
{
    return new ObservationDraft(
        sourceReference: 'document:archive',
        sourceName: 'Document archive',
        resourceReference: 'document:archive/one',
        producerReference: 'aleph:document-connector',
        producerName: 'Aleph document connector',
        ingestionRunReference: 'aleph:document-run/1',
        observedAt: new DateTimeImmutable('2026-08-28T12:00:00+00:00'),
        payload: 'Original source payload',
        contentType: 'text/plain',
        texts: [new TextDraft('document:body', 'text/plain', $text, 'en')],
    );
}

it('preserves explicit and source-payload text with provenance', function (): void {
    $observation = app(ObservationStore::class)->accept(textObservation())->observation;

    expect($observation->text('document:body'))->toHaveCount(1)
        ->and($observation->text('document:body')[0]->text)->toBe('Funes remembers the source.')
        ->and($observation->text('document:body')[0]->provenanceId)->toBe($observation->provenance[0]->id)
        ->and($observation->text('funes:source-payload')[0]->text)->toBe('Original source payload')
        ->and($observation->text('funes:source-payload')[0]->textHash)->toBe($observation->payloadHash);
});

it('keeps text acceptance idempotent and identity stable', function (): void {
    $store = app(ObservationStore::class);
    $first = $store->accept(textObservation())->observation;
    $replayed = $store->accept(textObservation())->observation;

    expect($replayed->id)->toBe($first->id)
        ->and($replayed->text('document:body')[0]->id)->toBe($first->text('document:body')[0]->id)
        ->and(DB::table('funes_observation_text')->count())->toBe(1);
});

it('appends changed text without changing observation identity', function (): void {
    $store = app(ObservationStore::class);
    $first = $store->accept(textObservation())->observation;
    $second = $store->accept(textObservation('A corrected searchable rendering.'))->observation;

    expect($second->id)->toBe($first->id)
        ->and($second->text('document:body'))->toHaveCount(2)
        ->and(DB::table('funes_observations')->count())->toBe(1)
        ->and(DB::table('funes_observation_text')->count())->toBe(2);
});

it('rebuilds the text projection from authoritative history', function (): void {
    app(ObservationStore::class)->accept(textObservation());
    $projection = app(TextProjection::class);

    expect($projection->rebuild())->toBe(2)
        ->and($projection->documents())->toHaveCount(2);

    DB::table('funes_text_projection')->delete();

    expect($projection->documents())->toBe([])
        ->and($projection->rebuild())->toBe(2)
        ->and($projection->documents()[0]->observationId)->not->toBe('');
});

it('rejects unnamespaced or empty historical text', function (): void {
    new TextDraft('body', 'text/plain', '');
})->throws(InvalidArgumentException::class);
