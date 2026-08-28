<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Sifrious\Funes\Identity\IdentityEvidenceNotFound;
use Sifrious\Funes\Identity\IdentityRegistry;
use Sifrious\Funes\Persistence\ObservationStore;
use Sifrious\Funes\Value\EntityKind;
use Sifrious\Funes\Value\ExternalIdentityClaim;
use Sifrious\Funes\Value\ObservationDraft;

uses(RefreshDatabase::class);

function identityObservation(string $payload = 'repository payload', string $observedAt = '2026-08-28T10:00:00+00:00'): ObservationDraft
{
    return new ObservationDraft(
        sourceReference: 'github:api',
        sourceName: 'GitHub API',
        resourceReference: 'https://api.github.test/repos/sifrious/funes',
        producerReference: 'aleph:github-connector',
        producerName: 'Aleph GitHub connector',
        observedAt: new DateTimeImmutable($observedAt),
        payload: $payload,
    );
}

function identityClaim(string $provenanceId, string $externalIdentifier = 'R_kgDOFunes'): ExternalIdentityClaim
{
    return new ExternalIdentityClaim(
        EntityKind::Repository,
        'github:api',
        $externalIdentifier,
        $provenanceId,
    );
}

it('resolves an external identity to one stable Funes entity', function (): void {
    $observation = app(ObservationStore::class)->accept(identityObservation())->observation;
    $registry = app(IdentityRegistry::class);

    $first = $registry->resolve(identityClaim($observation->provenance[0]->id));
    $second = $registry->resolve(identityClaim($observation->provenance[0]->id));

    expect($first->reference->id)->toStartWith('funes:')
        ->and($second->reference)->toEqual($first->reference)
        ->and($second->identities)->toHaveCount(1)
        ->and($second->identities[0]->externalIdentifier)->toBe('R_kgDOFunes')
        ->and($second->identities[0]->provenance)->toHaveCount(1)
        ->and($second->identities[0]->provenance[0]->observationId)->toBe($observation->id)
        ->and(DB::table('funes_entities')->count())->toBe(1)
        ->and(DB::table('funes_external_identities')->count())->toBe(1)
        ->and(DB::table('funes_identity_provenance')->count())->toBe(1);
});

it('retains later evidence without creating another entity', function (): void {
    $store = app(ObservationStore::class);
    $firstObservation = $store->accept(identityObservation())->observation;
    $secondObservation = $store->accept(identityObservation('changed repository payload', '2026-08-28T11:00:00+00:00'))->observation;
    $registry = app(IdentityRegistry::class);

    $first = $registry->resolve(identityClaim($firstObservation->provenance[0]->id));
    $second = $registry->resolve(identityClaim($secondObservation->provenance[0]->id));

    expect($second->reference)->toEqual($first->reference)
        ->and($second->identities[0]->provenance)->toHaveCount(2)
        ->and(DB::table('funes_entities')->count())->toBe(1);
});

it('finds stable entities by external identity and Funes reference', function (): void {
    $observation = app(ObservationStore::class)->accept(identityObservation())->observation;
    $registry = app(IdentityRegistry::class);
    $resolved = $registry->resolve(identityClaim($observation->provenance[0]->id));

    expect($registry->find(EntityKind::Repository, 'github:api', 'R_kgDOFunes')?->reference)->toEqual($resolved->reference)
        ->and($registry->get($resolved->reference)?->identities[0]->sourceReference)->toBe('github:api')
        ->and($registry->find(EntityKind::Repository, 'github:api', 'missing'))->toBeNull();
});

it('creates different entities for different source identifiers', function (): void {
    $observation = app(ObservationStore::class)->accept(identityObservation())->observation;
    $registry = app(IdentityRegistry::class);

    $first = $registry->resolve(identityClaim($observation->provenance[0]->id, 'R_kgDOFunes'));
    $second = $registry->resolve(identityClaim($observation->provenance[0]->id, 'R_kgDOAnother'));

    expect($second->reference)->not->toEqual($first->reference)
        ->and(DB::table('funes_entities')->count())->toBe(2);
});

it('rejects provenance from a different source', function (): void {
    $observation = app(ObservationStore::class)->accept(identityObservation())->observation;

    app(IdentityRegistry::class)->resolve(new ExternalIdentityClaim(
        EntityKind::Repository,
        'gitlab:api',
        'R_kgDOFunes',
        $observation->provenance[0]->id,
    ));
})->throws(IdentityEvidenceNotFound::class);
