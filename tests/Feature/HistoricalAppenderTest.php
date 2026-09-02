<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Sifrious\EventContract\EventEnvelope;
use Sifrious\Funes\Graph\AssertionType;
use Sifrious\Funes\Graph\HistoricalAppend;
use Sifrious\Funes\Graph\HistoricalAppendAuthorization;
use Sifrious\Funes\Graph\HistoricalAppendConflict;
use Sifrious\Funes\Graph\HistoricalAppender;
use Sifrious\Funes\Graph\HistoricalEntityDraft;
use Sifrious\Funes\Graph\HistoricalIdentifierDraft;
use Sifrious\Funes\Graph\HistoricalRelationDraft;
use Sifrious\Funes\Identity\IdentityRegistry;
use Sifrious\Funes\Persistence\ObservationStore;
use Sifrious\Funes\Value\EntityKind;
use Sifrious\Funes\Value\ExternalIdentityClaim;
use Sifrious\Funes\Value\ObservationDraft;
use Sifrious\ReferenceContract\CrossPackageReference;

uses(RefreshDatabase::class);

function mme2071Append(string $eventId = 'mme-1887:event:1', bool $broken = false): HistoricalAppend
{
    $store = app(ObservationStore::class);
    $kinds = [EntityKind::UserInput, EntityKind::Conversation, EntityKind::Twinkle, EntityKind::Plan, EntityKind::PlanStep, EntityKind::WorkKit, EntityKind::ExecutionRequest, EntityKind::Run, EntityKind::RunResult, EntityKind::Commit];
    $entities = [];
    foreach ($kinds as $kind) {
        $key = $kind->value;
        $observation = $store->accept(new ObservationDraft('mme-1887:fixture', 'MME-1887 fixture', "mme-1887:{$key}", 'aleph:test-adapter', 'Aleph test adapter', "mme-1887:run:{$eventId}", new DateTimeImmutable('2026-09-01T12:00:00+00:00'), $key))->observation;
        $entities[] = new HistoricalEntityDraft($key, new ExternalIdentityClaim($kind, 'mme-1887:fixture', "landing:{$key}:1", $observation->provenance[0]->id));
    }
    $event = new EventEnvelope($eventId, 'historical.graph-append', 'sifrious/landing', '1', new DateTimeImmutable('2026-09-01T12:00:00+00:00'), null, new DateTimeImmutable('2026-09-01T12:01:00+00:00'), [new CrossPackageReference('sifrious/landing', 'conversation', '1')], null, 'mme-1887', [], null, ['fixture' => 'MME-1887']);

    return new HistoricalAppend($event, new HistoricalAppendAuthorization('actor:user-1', 'tenant:sifrious'), $entities, [new HistoricalIdentifierDraft('commit', new ExternalIdentityClaim(EntityKind::Commit, 'mme-1887:fixture', 'git:sha:abc123', $entities[9]->identity->provenanceId))], [
        new HistoricalRelationDraft('user-input', 'started', 'conversation', 'landing:migration/mme-2071', AssertionType::Declared),
        new HistoricalRelationDraft('conversation', 'produced', 'twinkle', 'landing:migration/mme-2071', AssertionType::Observed),
        new HistoricalRelationDraft('twinkle', 'resulted-in', $broken ? 'missing' : 'plan', 'landing:migration/mme-2071', AssertionType::Declared),
        new HistoricalRelationDraft('plan', 'concerns', 'commit', 'kilgore:analysis:1', AssertionType::Inferred, ['evidence:conversation:1'], .82),
    ]);
}

it('persists the complete MME-1887 fixture through stable identities and replays once', function (): void {
    $append = mme2071Append();
    app(HistoricalAppender::class)->append($append);
    app(HistoricalAppender::class)->append($append);
    expect(DB::table('funes_graph_appends')->count())->toBe(1)->and(DB::table('funes_entities')->count())->toBe(10)->and(DB::table('funes_external_identities')->count())->toBe(11)->and(DB::table('funes_entity_relations')->count())->toBe(4)->and(DB::table('funes_entity_relation_evidence')->count())->toBe(1);
    expect(app(IdentityRegistry::class)->find(EntityKind::Commit, 'mme-1887:fixture', 'git:sha:abc123'))->not->toBeNull();
});

it('rejects conflicting event reuse', function (): void {
    $append = mme2071Append();
    app(HistoricalAppender::class)->append($append);
    $changed = new HistoricalAppend($append->event, new HistoricalAppendAuthorization('actor:other', 'tenant:sifrious'), $append->entities, $append->identifiers, $append->relations);
    expect(fn () => app(HistoricalAppender::class)->append($changed))->toThrow(HistoricalAppendConflict::class);
});

it('rolls back entities identities relations and receipt after a mid-write failure', function (): void {
    expect(fn () => app(HistoricalAppender::class)->append(mme2071Append(broken: true)))->toThrow(HistoricalAppendConflict::class);
    expect(DB::table('funes_graph_appends')->count())->toBe(0)->and(DB::table('funes_entities')->count())->toBe(0)->and(DB::table('funes_external_identities')->count())->toBe(0)->and(DB::table('funes_entity_relations')->count())->toBe(0);
});

it('keeps inferred assertions distinguishable and independently removable', function (): void {
    app(HistoricalAppender::class)->append(mme2071Append());
    expect(DB::table('funes_entity_relations')->where('assertion_type', 'inferred')->count())->toBe(1)->and(DB::table('funes_entity_relations')->where('assertion_type', 'declared')->count())->toBe(2);
    DB::table('funes_entity_relations')->where('assertion_type', 'inferred')->delete();
    expect(DB::table('funes_entity_relations')->count())->toBe(3);
});
