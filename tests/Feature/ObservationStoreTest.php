<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Sifrious\Funes\Association\EntityAssociationDraft;
use Sifrious\Funes\Association\EntityAssociationRole;
use Sifrious\Funes\Diagram\SentenceDiagramService;
use Sifrious\Funes\Persistence\ObservationConflict;
use Sifrious\Funes\Persistence\ObservationNotFound;
use Sifrious\Funes\Persistence\ObservationStore;
use Sifrious\Funes\Reference\CrossPackageReference;
use Sifrious\Funes\Relationship\HistoricalRelationshipDraft;
use Sifrious\Funes\Relationship\HistoricalRelationshipType;
use Sifrious\Funes\Relationship\RelationshipDeclarationDraft;
use Sifrious\Funes\Value\DerivationProcess;
use Sifrious\Funes\Value\Discovery;
use Sifrious\Funes\Value\ExtractionDisposition;
use Sifrious\Funes\Value\ExtractionDraft;
use Sifrious\Funes\Value\HistoricalRecordType;
use Sifrious\Funes\Value\IngestionRun;
use Sifrious\Funes\Value\MetadataDraft;
use Sifrious\Funes\Value\ObservationDisposition;
use Sifrious\Funes\Value\ObservationDraft;
use Sifrious\Funes\Value\Producer;
use Sifrious\Funes\Value\ProducerContext;

use function Sifrious\Funes\diagram;

uses(RefreshDatabase::class);

function observationDraft(
    string $payload = '<html>first</html>',
    string $observedAt = '2026-08-26T12:00:00+00:00',
    ?array $metadata = null,
    ?array $associations = null,
    ?array $relationships = null,
): ObservationDraft {
    return new ObservationDraft(
        sourceReference: 'website:example',
        sourceName: 'Example website',
        resourceReference: 'https://example.test/articles/one',
        producerReference: 'aleph:connector/web',
        producerName: 'Aleph web connector',
        ingestionRunReference: 'aleph:run/2026-08-26T12:00:00Z',
        observedAt: new DateTimeImmutable($observedAt),
        payload: $payload,
        occurredAt: new DateTimeImmutable('2026-08-26T11:55:00+00:00'),
        transformationLineage: ['aleph:fetch/1'],
        contentType: 'text/html',
        metadata: $metadata ?? [new MetadataDraft('http:response', '1', ['status' => 200])],
        associations: $associations ?? [],
        relationships: $relationships ?? [],
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
        ->and($found?->provenance[0]->ingestionRun->reference)->toBe('aleph:run/2026-08-26T12:00:00Z')
        ->and($found?->provenance[0]->occurredAt?->format(DATE_ATOM))->toBe('2026-08-26T11:55:00+00:00')
        ->and($found?->provenance[0]->transformationLineage)->toBe(['aleph:fetch/1'])
        ->and($found?->metadata('http:response'))->toHaveCount(1)
        ->and($found?->metadata('http:response', '1')[0]->attributes)->toBe(['status' => 200])
        ->and($found?->metadata[0]->provenanceId)->toBe($found?->provenance[0]->id)
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
        ->and(DB::table('funes_observation_provenance')->count())->toBe(1)
        ->and(DB::table('funes_observation_metadata')->count())->toBe(1);
});

it('returns typed entity associations with their own provenance', function (): void {
    $store = app(ObservationStore::class);
    $project = new CrossPackageReference('sifrious/landing', 'project', 'project_01');
    $repository = new CrossPackageReference('sifrious/aleph', 'repository', 'github:R_01');
    $user = new CrossPackageReference('sifrious/accounts', 'user', 'user_01');
    $agent = new CrossPackageReference('sifrious/logres', 'agent', 'agent_01');
    $conversation = new CrossPackageReference('sifrious/logres', 'conversation', 'conversation_01');
    $file = new CrossPackageReference('sifrious/aleph', 'file', 'artifact_01');
    $task = new CrossPackageReference('sifrious/titan', 'task', 'task_01');
    $associations = [
        new EntityAssociationDraft(EntityAssociationRole::Context, $project),
        new EntityAssociationDraft(EntityAssociationRole::Context, $repository),
        new EntityAssociationDraft(EntityAssociationRole::Actor, $user),
        new EntityAssociationDraft(EntityAssociationRole::Actor, $agent),
        new EntityAssociationDraft(EntityAssociationRole::Context, $conversation),
        new EntityAssociationDraft(EntityAssociationRole::Artifact, $file),
        new EntityAssociationDraft(EntityAssociationRole::Target, $task),
    ];

    $observation = $store->accept(observationDraft(associations: $associations))->observation;
    $taskAssociations = $store->associationsTo($task);

    expect($observation->associations)->toHaveCount(7)
        ->and($observation->associated(EntityAssociationRole::Actor))->toHaveCount(2)
        ->and($observation->associated(EntityAssociationRole::Context, 'repository'))->toHaveCount(1)
        ->and($taskAssociations)->toHaveCount(1)
        ->and($taskAssociations[0]->observationId)->toBe($observation->id)
        ->and($taskAssociations[0]->entity->equals($task))->toBeTrue()
        ->and($taskAssociations[0]->provenanceIds)->toBe([$observation->provenance[0]->id]);
});

it('keeps association facts idempotent while appending source provenance', function (): void {
    $store = app(ObservationStore::class);
    $project = new CrossPackageReference('sifrious/landing', 'project', 'project_01');
    $association = new EntityAssociationDraft(EntityAssociationRole::Context, $project);

    $first = $store->accept(observationDraft(associations: [$association]))->observation;
    $retry = $store->accept(observationDraft(associations: [$association]))->observation;
    $later = $store->accept(observationDraft(
        observedAt: '2026-08-27T12:00:00+00:00',
        associations: [$association],
    ))->observation;

    expect($retry->associations[0]->id)->toBe($first->associations[0]->id)
        ->and($later->associations[0]->id)->toBe($first->associations[0]->id)
        ->and($later->associations[0]->provenanceIds)->toHaveCount(2)
        ->and(DB::table('funes_entity_associations')->count())->toBe(1)
        ->and(DB::table('funes_entity_association_provenance')->count())->toBe(2);
});

it('rejects association inputs outside the typed contract', function (): void {
    observationDraft(associations: ['project_01']);
})->throws(InvalidArgumentException::class);

it('preserves typed relationships between historical events without embedding records', function (): void {
    $store = app(ObservationStore::class);
    $original = $store->accept(observationDraft())->observation;
    $relationship = new HistoricalRelationshipDraft(
        HistoricalRelationshipType::Corrects,
        $original->reference(),
    );
    $correction = $store->accept(observationDraft(
        payload: '<html>corrected</html>',
        observedAt: '2026-08-27T12:00:00+00:00',
        relationships: [$relationship],
    ))->observation;
    $incoming = $store->relationshipsTo($original->reference());

    expect($correction->related(HistoricalRelationshipType::Corrects))->toHaveCount(1)
        ->and($correction->relationships[0]->target->equals($original->reference()))->toBeTrue()
        ->and($correction->relationships[0]->provenanceIds)->toBe([$correction->provenance[0]->id])
        ->and($incoming)->toHaveCount(1)
        ->and($incoming[0]->observationId)->toBe($correction->id)
        ->and(DB::table('funes_observations')->count())->toBe(2)
        ->and(json_decode((string) DB::table('funes_historical_relationships')->value('target_reference'), true, flags: JSON_THROW_ON_ERROR))
        ->toBe($original->reference()->toArray());
});

it('keeps relationship facts idempotent while appending source provenance', function (): void {
    $store = app(ObservationStore::class);
    $original = $store->accept(observationDraft())->observation;
    $relationship = new HistoricalRelationshipDraft(HistoricalRelationshipType::References, $original->reference());
    $draft = observationDraft(
        payload: '<html>related</html>',
        observedAt: '2026-08-27T12:00:00+00:00',
        relationships: [$relationship],
    );

    $first = $store->accept($draft)->observation;
    $retry = $store->accept($draft)->observation;
    $later = $store->accept(observationDraft(
        payload: '<html>related</html>',
        observedAt: '2026-08-28T12:00:00+00:00',
        relationships: [$relationship],
    ))->observation;

    expect($retry->relationships[0]->id)->toBe($first->relationships[0]->id)
        ->and($later->relationships[0]->id)->toBe($first->relationships[0]->id)
        ->and($later->relationships[0]->provenanceIds)->toHaveCount(2)
        ->and(DB::table('funes_historical_relationships')->count())->toBe(1)
        ->and(DB::table('funes_historical_relationship_provenance')->count())->toBe(2);
});

it('rejects non-event and missing internal historical references', function (): void {
    expect(fn () => new HistoricalRelationshipDraft(
        HistoricalRelationshipType::Related,
        new CrossPackageReference('sifrious/landing', 'project', 'project_01'),
    ))->toThrow(InvalidArgumentException::class);

    $store = app(ObservationStore::class);
    $missing = new HistoricalRelationshipDraft(
        HistoricalRelationshipType::Related,
        new CrossPackageReference('sifrious/funes', 'observation', '01K00000000000000000000000'),
    );

    expect(fn () => $store->accept(observationDraft(relationships: [$missing])))
        ->toThrow(ObservationNotFound::class)
        ->and(DB::table('funes_observations')->count())->toBe(0);
});

it('preserves explicit causal and parent declarations with provenance', function (): void {
    $store = app(ObservationStore::class);
    $parent = $store->accept(observationDraft())->observation;
    $causal = new HistoricalRelationshipDraft(
        HistoricalRelationshipType::CausedBy,
        $parent->reference(),
        new RelationshipDeclarationDraft('github:event/caused_by', 'delivery_01'),
    );
    $hierarchy = new HistoricalRelationshipDraft(
        HistoricalRelationshipType::ChildOf,
        $parent->reference(),
        new RelationshipDeclarationDraft('linear:issue/parent_id', 'MME-100'),
    );
    $child = $store->accept(observationDraft(
        payload: '<html>declared child</html>',
        observedAt: '2026-08-27T12:00:00+00:00',
        relationships: [$causal, $hierarchy],
    ))->observation;

    expect($child->related(HistoricalRelationshipType::CausedBy))->toHaveCount(1)
        ->and($child->related(HistoricalRelationshipType::ChildOf))->toHaveCount(1)
        ->and($child->relationships[0]->declarations[0]->sourceLocator)->toBe('github:event/caused_by')
        ->and($child->relationships[0]->declarations[0]->declaredValue)->toBe('delivery_01')
        ->and($child->relationships[0]->declarations[0]->provenanceId)->toBe($child->provenance[0]->id)
        ->and($child->relationships[1]->declarations[0]->sourceLocator)->toBe('linear:issue/parent_id')
        ->and(DB::table('funes_relationship_declarations')->count())->toBe(2);
});

it('requires declarations for causal semantics and never promotes an ordinary relation', function (): void {
    $target = new CrossPackageReference('sifrious/funes', 'observation', '01K00000000000000000000000');

    expect(fn () => new HistoricalRelationshipDraft(HistoricalRelationshipType::CausedBy, $target))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => new HistoricalRelationshipDraft(HistoricalRelationshipType::ChildOf, $target))
        ->toThrow(InvalidArgumentException::class)
        ->and(new HistoricalRelationshipDraft(HistoricalRelationshipType::Related, $target)->declaration)->toBeNull()
        ->and(fn () => new RelationshipDeclarationDraft('caused_by', 'delivery_01'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => new RelationshipDeclarationDraft('github:event/caused_by', ''))
        ->toThrow(InvalidArgumentException::class);
});

it('keeps explicit relationship declarations idempotent across retries', function (): void {
    $store = app(ObservationStore::class);
    $cause = $store->accept(observationDraft())->observation;
    $relationship = new HistoricalRelationshipDraft(
        HistoricalRelationshipType::CausedBy,
        $cause->reference(),
        new RelationshipDeclarationDraft('github:event/caused_by', 'delivery_01'),
    );
    $draft = observationDraft(
        payload: '<html>effect</html>',
        observedAt: '2026-08-27T12:00:00+00:00',
        relationships: [$relationship],
    );

    $first = $store->accept($draft)->observation;
    $retry = $store->accept($draft)->observation;
    $later = $store->accept(observationDraft(
        payload: '<html>effect</html>',
        observedAt: '2026-08-28T12:00:00+00:00',
        relationships: [$relationship],
    ))->observation;

    expect($retry->relationships[0]->id)->toBe($first->relationships[0]->id)
        ->and($later->relationships[0]->id)->toBe($first->relationships[0]->id)
        ->and($later->relationships[0]->declarations)->toHaveCount(2)
        ->and($later->relationships[0]->provenanceIds)->toHaveCount(2)
        ->and(DB::table('funes_historical_relationships')->count())->toBe(1)
        ->and(DB::table('funes_relationship_declarations')->count())->toBe(2);
});

it('appends namespaced metadata without changing observation identity', function (): void {
    $store = app(ObservationStore::class);
    $first = $store->accept(observationDraft())->observation;
    $second = $store->accept(observationDraft(
        metadata: [new MetadataDraft('http:response', '2', ['status' => 200, 'cache' => 'hit'])],
    ))->observation;

    expect($second->id)->toBe($first->id)
        ->and($second->metadata('http:response'))->toHaveCount(2)
        ->and($second->metadata('http:response', '1'))->toHaveCount(1)
        ->and($second->metadata('http:response', '2')[0]->attributes['cache'])->toBe('hit')
        ->and(DB::table('funes_observations')->count())->toBe(1)
        ->and(DB::table('funes_observation_metadata')->count())->toBe(2);
});

it('rejects unnamespaced metadata', function (): void {
    new MetadataDraft('response', '1', ['status' => 200]);
})->throws(InvalidArgumentException::class);

it('returns pre-contract metadata as an explicit legacy assertion', function (): void {
    $store = app(ObservationStore::class);
    $observation = $store->accept(observationDraft(metadata: []))->observation;

    DB::table('funes_observations')
        ->where('id', $observation->id)
        ->update(['metadata' => json_encode(['status' => 200], JSON_THROW_ON_ERROR)]);

    $legacy = $store->get($observation->id)?->metadata('funes:legacy');

    expect($legacy)->toHaveCount(1)
        ->and($legacy[0]->schemaVersion)->toBe('1')
        ->and($legacy[0]->attributes)->toBe(['status' => 200])
        ->and($legacy[0]->provenanceId)->toBe($observation->provenance[0]->id);
});

it('rejects incomplete producer provenance', function (): void {
    new ObservationDraft(
        sourceReference: 'website:example',
        sourceName: 'Example website',
        resourceReference: 'https://example.test/articles/one',
        producerReference: '',
        producerName: 'Aleph web connector',
        ingestionRunReference: 'aleph:run/1',
        observedAt: new DateTimeImmutable('2026-08-26T12:00:00+00:00'),
        payload: 'body',
    );
})->throws(InvalidArgumentException::class);

it('rejects missing ingestion-run provenance', function (): void {
    new ObservationDraft(
        sourceReference: 'website:example',
        sourceName: 'Example website',
        resourceReference: 'https://example.test/articles/one',
        producerReference: 'aleph:connector/web',
        producerName: 'Aleph web connector',
        ingestionRunReference: '',
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
        new ProducerContext(new Producer('kilgore:extractor/article', 'Kilgore article extractor'), new IngestionRun('kilgore:run/1')),
        ['title' => 'First'],
    ));
    $failure = $store->recordExtraction(new ExtractionDraft(
        $observation->id,
        'article',
        '2',
        new ProducerContext(new Producer('kilgore:extractor/article', 'Kilgore article extractor'), new IngestionRun('kilgore:run/1')),
        failure: 'Unsupported document',
    ));

    expect($success->succeeded())->toBeTrue()
        ->and($observation->type())->toBe(HistoricalRecordType::Observed)
        ->and($success->type())->toBe(HistoricalRecordType::Derived)
        ->and($success->observationId)->toBe($observation->id)
        ->and($success->process())->toEqual(new DerivationProcess('article', '1'))
        ->and($success->producerContexts[0]->producer->reference)->toBe('kilgore:extractor/article')
        ->and($success->producerContexts[0]->ingestionRun->reference)->toBe('kilgore:run/1')
        ->and($success->result)->toBe(['title' => 'First'])
        ->and($failure->succeeded())->toBeFalse()
        ->and($failure->failure)->toBe('Unsupported document')
        ->and($store->find('website:example', 'https://example.test/articles/one')?->payload)->toBe('<html>first</html>');
});

it('retrieves typed extracted representations and distinguishes every outcome', function (): void {
    $store = app(ObservationStore::class);
    $observation = $store->accept(observationDraft())->observation;
    $context = new ProducerContext(new Producer('kilgore:extractor/article', 'Kilgore article extractor'), new IngestionRun('kilgore:run/typed'));

    $success = $store->recordExtraction(new ExtractionDraft(
        $observation->id,
        'article-parser',
        '1',
        $context,
        ['title' => 'First'],
        representationType: 'article',
    ));
    $unsupported = $store->recordExtraction(new ExtractionDraft(
        $observation->id,
        'table-parser',
        '1',
        $context,
        failure: 'No tables were present.',
        representationType: 'table',
        disposition: ExtractionDisposition::Unsupported,
        failureCode: 'document_has_no_tables',
        failureDetails: ['media_type' => 'text/html'],
    ));

    expect($success->inputHash)->toBe(hash('sha256', '<html>first</html>'))
        ->and($success->disposition)->toBe(ExtractionDisposition::Succeeded)
        ->and($unsupported->disposition)->toBe(ExtractionDisposition::Unsupported)
        ->and($unsupported->failureCode)->toBe('document_has_no_tables')
        ->and($unsupported->failureDetails)->toBe(['media_type' => 'text/html'])
        ->and($store->extraction($observation->id, 'table', 'table-parser', '1')?->id)->toBe($unsupported->id)
        ->and($store->extraction($observation->id, 'missing', 'parser', '1'))->toBeNull()
        ->and($store->extractions($observation->id))->toHaveCount(2)
        ->and($store->get($observation->id)?->payload)->toBe('<html>first</html>');
});

it('prevents a derived result from entering observation acceptance', function (): void {
    $store = app(ObservationStore::class);
    $observation = $store->accept(observationDraft())->observation;
    $derived = $store->recordExtraction(new ExtractionDraft(
        $observation->id,
        'summary',
        '1',
        new ProducerContext(new Producer('kilgore:extractor/summary', 'Kilgore summary extractor'), new IngestionRun('kilgore:run/2')),
        ['text' => 'A later interpretation'],
    ));

    $store->accept($derived);
})->throws(TypeError::class);

it('rejects an unnamed derivation process', function (): void {
    new ExtractionDraft(
        '01K00000000000000000000000',
        '',
        '1',
        new ProducerContext(new Producer('kilgore:extractor/summary', 'Kilgore summary extractor'), new IngestionRun('kilgore:run/2')),
        ['text' => 'A later interpretation'],
    );
})->throws(InvalidArgumentException::class);

it('makes repeated extraction recording idempotent and rejects conflicting reuse', function (): void {
    $store = app(ObservationStore::class);
    $observation = $store->accept(observationDraft())->observation;
    $context = new ProducerContext(new Producer('kilgore:extractor/article', 'Kilgore article extractor'), new IngestionRun('kilgore:run/1'));
    $draft = new ExtractionDraft($observation->id, 'article', '1', $context, ['title' => 'First']);

    $first = $store->recordExtraction($draft);
    $second = $store->recordExtraction($draft);

    expect($second->id)->toBe($first->id)
        ->and(fn () => $store->recordExtraction(new ExtractionDraft(
            $observation->id,
            'article',
            '1',
            $context,
            ['title' => 'Changed'],
        )))->toThrow(ObservationConflict::class);
});

it('appends producer runs to an unchanged derived result', function (): void {
    $store = app(ObservationStore::class);
    $observation = $store->accept(observationDraft())->observation;

    $first = $store->recordExtraction(new ExtractionDraft(
        $observation->id,
        'article',
        '1',
        new ProducerContext(new Producer('kilgore:extractor/article', 'Kilgore article extractor'), new IngestionRun('kilgore:run/1')),
        ['title' => 'First'],
    ));
    $second = $store->recordExtraction(new ExtractionDraft(
        $observation->id,
        'article',
        '1',
        new ProducerContext(new Producer('kilgore:extractor/article', 'Kilgore article extractor'), new IngestionRun('kilgore:run/2')),
        ['title' => 'First'],
    ));

    expect($second->id)->toBe($first->id)
        ->and($second->producerContexts)->toHaveCount(2)
        ->and(DB::table('funes_extractions')->count())->toBe(1)
        ->and(DB::table('funes_extraction_provenance')->count())->toBe(2);
});

it('produces grammar graph and svg entirely offline', function (): void {
    $result = diagram('The cat eats fish.');

    expect($result['source'])->toBe('The cat eats fish.')
        ->and($result['grammar_graph']['nodes'])->not->toBeEmpty()
        ->and($result['grammar_graph']['edges'])->not->toBeEmpty()
        ->and($result['svg'])->toContain('<svg')
        ->and($result['provenance']['mode'])->toBe('offline')
        ->and($result['provenance']['llm_used'])->toBeFalse()
        ->and($result['timings']['total_ms'])->toBeFloat();
});

it('records diagram re-runs as new extraction versions without overwriting source', function (): void {
    $store = app(ObservationStore::class);
    $diagrammer = app(SentenceDiagramService::class);
    $observation = $store->accept(observationDraft(
        payload: 'Mary writes a clear note.',
        metadata: [],
    ))->observation;

    $first = $diagrammer->diagramAndRecord(
        $observation->id,
        new ProducerContext(new Producer('funes:diagram/service', 'Funes diagram service'), new IngestionRun('funes:diagram-run/1')),
    );
    $second = $diagrammer->diagramAndRecord(
        $observation->id,
        new ProducerContext(new Producer('funes:diagram/service', 'Funes diagram service'), new IngestionRun('funes:diagram-run/2')),
    );

    $stored = DB::table('funes_extractions')
        ->where('observation_id', $observation->id)
        ->where('extractor', SentenceDiagramService::EXTRACTOR)
        ->orderBy('version')
        ->pluck('version')
        ->all();
    $source = $store->get($observation->id);

    expect($stored)->toBe(['1', '2'])
        ->and($first->result['source'])->toBe('Mary writes a clear note.')
        ->and($second->result['source'])->toBe('Mary writes a clear note.')
        ->and($second->result['provenance']['llm_used'])->toBeFalse()
        ->and($source?->payload)->toBe('Mary writes a clear note.');
});
