<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Sifrious\AuthorizationContract\ActorContext;
use Sifrious\AuthorizationContract\ActorKind;
use Sifrious\AuthorizationContract\AuthorizationContext;
use Sifrious\AuthorizationContract\TenantScope;
use Sifrious\EventContract\EventEnvelope;
use Sifrious\Funes\Association\EntityAssociationDraft;
use Sifrious\Funes\Association\EntityAssociationRole;
use Sifrious\Funes\Graph\HistoricalAppend;
use Sifrious\Funes\Graph\HistoricalAppendAuthorization;
use Sifrious\Funes\Graph\HistoricalAppender;
use Sifrious\Funes\Graph\HistoricalEntityDraft;
use Sifrious\Funes\Graph\HistoricalIdentifierDraft;
use Sifrious\Funes\Persistence\ObservationStore;
use Sifrious\Funes\Value\EntityKind;
use Sifrious\Funes\Value\ExternalIdentityClaim;
use Sifrious\Funes\Value\ObservationDisposition;
use Sifrious\Funes\Value\ObservationDraft;
use Sifrious\ReferenceContract\CrossPackageReference;

uses(RefreshDatabase::class);

it('accepts and idempotently replays a versioned Landing registry reference', function (): void {
    $landingReference = CrossPackageReference::fromArray([
        'contract' => 'sifrious.cross-package-reference',
        'contract_version' => 1,
        'owner' => 'sifrious/landing',
        'type' => 'repository',
        'id' => 'a2aee225-1f2d-521f-b92e-25baaaf30eef',
        'object_version' => hash('sha256', '{"content":{"kind":"github","name":"sifrious"},"schema":"sifrious.repository","schema_version":1}'),
        'provenance' => [
            'contract' => 'sifrious.cross-package-reference',
            'contract_version' => 1,
            'owner' => 'sifrious/funes',
            'type' => 'source-locator',
            'id' => 'landing:test',
            'object_version' => null,
            'provenance' => null,
        ],
    ]);
    $draft = new ObservationDraft(
        sourceReference: 'landing:reference-registry',
        sourceName: 'Landing reference registry',
        resourceReference: 'landing:repositories/42',
        producerReference: 'landing:reference-mapper/repository-v1',
        producerName: 'Landing repository reference mapper',
        ingestionRunReference: 'landing:migration/mme-2071',
        observedAt: new DateTimeImmutable('2026-09-01T12:00:00+00:00'),
        payload: json_encode($landingReference, JSON_THROW_ON_ERROR),
        contentType: 'application/vnd.sifrious.cross-package-reference+json',
        associations: [new EntityAssociationDraft(EntityAssociationRole::Subject, $landingReference)],
    );
    $store = app(ObservationStore::class);

    $first = $store->accept($draft);
    $replayed = $store->accept($draft);
    $associations = $store->associationsTo($landingReference);
    $historicalAppend = new HistoricalAppend(
        new EventEnvelope('landing:reference-registry:repositories:42:'.$landingReference->objectVersion, 'landing.reference-mapped', 'sifrious/landing', '1', new DateTimeImmutable('2026-09-01T12:00:00+00:00'), null, new DateTimeImmutable('2026-09-01T12:01:00+00:00'), [$landingReference], null, null, [], null, ['table' => 'repositories']),
        new HistoricalAppendAuthorization(new AuthorizationContext(
            new ActorContext(new CrossPackageReference('sifrious/zahir', 'service', 'landing-migration'), ActorKind::Service),
            TenantScope::forTenant('organization', new CrossPackageReference('sifrious/zahir', 'organization', 'sifrious')),
        )),
        [new HistoricalEntityDraft($landingReference->key(), new ExternalIdentityClaim(EntityKind::Repository, 'landing:reference-registry', $landingReference->id, $first->observation->provenance[0]->id))],
        [new HistoricalIdentifierDraft($landingReference->key(), new ExternalIdentityClaim(EntityKind::Repository, 'landing:reference-registry', 'landing:repositories/42', $first->observation->provenance[0]->id))],
    );
    $appender = app(HistoricalAppender::class);
    $appender->append($historicalAppend);
    $appender->append($historicalAppend);

    expect($first->disposition)->toBe(ObservationDisposition::First)
        ->and($replayed->disposition)->toBe(ObservationDisposition::Unchanged)
        ->and($replayed->observation->id)->toBe($first->observation->id)
        ->and($associations)->toHaveCount(1)
        ->and($associations[0]->entity->equals($landingReference))->toBeTrue()
        ->and($associations[0]->provenanceIds)->toHaveCount(1)
        ->and(DB::table('funes_graph_appends')->count())->toBe(1)
        ->and(DB::table('funes_entities')->count())->toBe(1)
        ->and(DB::table('funes_external_identities')->count())->toBe(2);
});
