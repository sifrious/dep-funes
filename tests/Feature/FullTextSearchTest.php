<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Sifrious\AuthorizationContract\ActorContext;
use Sifrious\AuthorizationContract\ActorKind;
use Sifrious\AuthorizationContract\AuthorizationContext;
use Sifrious\AuthorizationContract\TenantScope;
use Sifrious\Funes\Assertion\AbstractHistoricalAssertion;
use Sifrious\Funes\Assertion\DeclaredHistoricalAssertion;
use Sifrious\Funes\Assertion\HistoricalAssertionStore;
use Sifrious\Funes\Assertion\ObservedHistoricalAssertion;
use Sifrious\Funes\Search\FullTextSearch;
use Sifrious\Funes\Search\SearchQuery;
use Sifrious\Funes\Value\SourceLocator;
use Sifrious\ReferenceContract\CrossPackageReference;

uses(RefreshDatabase::class);

/**
 * The MME-1887 spine fixture, reduced to the assertions a search must find: a commit
 * and its message, the ticket the work came from, and the private input that started
 * it. Two accounts hold history so every read can be checked for leakage.
 */
const SEARCH_TENANT = 'org:acme';

const OTHER_TENANT = 'org:rival';

function searchTenant(string $organization): TenantScope
{
    return TenantScope::forTenant('organization', new CrossPackageReference('sifrious/landing', 'organization', $organization));
}

function searchContext(string $organization = SEARCH_TENANT, string $actor = 'user:mary'): AuthorizationContext
{
    return new AuthorizationContext(
        new ActorContext(new CrossPackageReference('sifrious/landing', 'user', $actor), ActorKind::Human),
        searchTenant($organization),
    );
}

/**
 * @param  class-string<AbstractHistoricalAssertion>  $class
 */
function searchClaim(
    string $id,
    CrossPackageReference $subject,
    string $predicate,
    mixed $value,
    string $organization = SEARCH_TENANT,
    string $sourceReference = 'github:repository',
    string $recordedAt = '2026-08-31T12:00:00Z',
    string $class = ObservedHistoricalAssertion::class,
): AbstractHistoricalAssertion {
    return new $class(
        $id,
        $subject,
        $predicate,
        $value,
        new SourceLocator($sourceReference, 'GitHub', 'github:R_kgDOExample'),
        searchTenant($organization),
        new DateTimeImmutable('2026-08-29T12:00:00Z'),
        new DateTimeImmutable('2026-08-30T12:00:00Z'),
        new DateTimeImmutable($recordedAt),
    );
}

function searchCommit(): CrossPackageReference
{
    return new CrossPackageReference('sifrious/funes', 'commit', '9f1c2ab3d4e5f60718293a4b5c6d7e8f90a1b2c3');
}

function searchTicket(): CrossPackageReference
{
    return new CrossPackageReference('sifrious/landing', 'external-work-item', 'MME-1887');
}

function searchInput(string $id = 'input:1'): CrossPackageReference
{
    return new CrossPackageReference('sifrious/elwin', 'user-input', $id);
}

function seedSpine(): void
{
    $store = app(HistoricalAssertionStore::class);
    $mine = searchContext();

    $store->append(searchClaim(
        'assert:commit-message',
        searchCommit(),
        'message',
        'Stop losing the instant a source reported',
    ), $mine);

    $store->append(searchClaim(
        'assert:ticket-title',
        searchTicket(),
        'title',
        ['title' => 'Define the end-to-end intent-to-result causal acceptance fixture', 'state' => 'in-progress'],
        sourceReference: 'linear:workspace',
        recordedAt: '2026-08-31T09:00:00Z',
        class: DeclaredHistoricalAssertion::class,
    ), $mine);

    $store->append(searchClaim(
        'assert:private-input',
        searchInput(),
        'text',
        'Please audit the peristaltic timestamp precision before we ship',
        sourceReference: 'elwin:conversation',
        recordedAt: '2026-08-31T08:00:00Z',
    ), $mine);

    $store->append(searchClaim(
        'assert:rival-input',
        searchInput('input:rival'),
        'text',
        'Their own peristaltic notes, which are none of our business',
        organization: OTHER_TENANT,
        sourceReference: 'elwin:conversation',
    ), searchContext(OTHER_TENANT, 'user:rival'));

    app(FullTextSearch::class)->rebuild();
}

it('resolves full-text search through the container', function (): void {
    expect(app(FullTextSearch::class))->toBeInstanceOf(FullTextSearch::class);
});

it('returns the stable commit entity for its commit message', function (): void {
    seedSpine();

    $results = app(FullTextSearch::class)->search(new SearchQuery('losing the instant'), searchContext());

    expect($results->total)->toBe(1)
        ->and($results->hits[0]->subject()->equals(searchCommit()))->toBeTrue()
        ->and($results->hits[0]->field)->toBe('value')
        ->and($results->hits[0]->snippet)->toContain('Stop losing the instant')
        ->and($results->hits[0]->assertion->stableIdentity())->toBe('assert:commit-message');
});

it('returns the stable work item for a title nested inside a structured value', function (): void {
    seedSpine();

    $results = app(FullTextSearch::class)->search(new SearchQuery('causal acceptance fixture'), searchContext());

    expect($results->total)->toBe(1)
        ->and($results->hits[0]->subject()->equals(searchTicket()))->toBeTrue()
        ->and($results->hits[0]->field)->toBe('value.title');
});

it('carries source and provenance with every hit so a result can be cited', function (): void {
    seedSpine();

    $hit = app(FullTextSearch::class)->search(new SearchQuery('losing the instant'), searchContext())->hits[0];

    expect($hit->assertion->source()->sourceReference)->toBe('github:repository')
        ->and($hit->assertion->source()->resourceReference)->toBe('github:R_kgDOExample')
        ->and($hit->assertion->assertionType()->value)->toBe('observed')
        ->and($hit->toArray()['subject'])->toBe(searchCommit()->toArray())
        ->and($hit->toArray()['recorded_at'])->toBe('2026-08-31T12:00:00.000000+00:00');
});

it('returns private input to the account that holds it', function (): void {
    seedSpine();

    $results = app(FullTextSearch::class)->search(new SearchQuery('peristaltic'), searchContext());

    expect($results->total)->toBe(1)
        ->and($results->hits[0]->subject()->equals(searchInput()))->toBeTrue();
});

it('hides another tenant history from both the hits and the total', function (): void {
    seedSpine();

    $rival = app(FullTextSearch::class)->search(new SearchQuery('peristaltic'), searchContext(OTHER_TENANT, 'user:rival'));

    expect($rival->total)->toBe(1)
        ->and($rival->hits[0]->subject()->equals(searchInput('input:rival')))->toBeTrue()
        ->and($rival->hits[0]->assertion->stableIdentity())->toBe('assert:rival-input');
});

it('returns nothing to a tenant that holds no history rather than leaking a count', function (): void {
    seedSpine();

    $stranger = app(FullTextSearch::class)->search(new SearchQuery('peristaltic'), searchContext('org:stranger', 'user:nobody'));

    expect($stranger->total)->toBe(0)
        ->and($stranger->isEmpty())->toBeTrue();
});

it('applies the tenant boundary to an unscoped caller too', function (): void {
    seedSpine();

    $unscoped = new AuthorizationContext(
        new ActorContext(new CrossPackageReference('sifrious/landing', 'user', 'user:mary'), ActorKind::Human),
        TenantScope::unscoped(),
    );

    expect(app(FullTextSearch::class)->search(new SearchQuery('peristaltic'), $unscoped)->total)->toBe(0);
});

it('narrows a broad query by subject type, predicate, and source', function (): void {
    seedSpine();

    $search = app(FullTextSearch::class);

    expect($search->search(new SearchQuery('the'), searchContext())->total)->toBe(3)
        ->and($search->search(new SearchQuery('the', subjectTypes: ['commit']), searchContext())->total)->toBe(1)
        ->and($search->search(new SearchQuery('the', predicates: ['message']), searchContext())->total)->toBe(1)
        ->and($search->search(new SearchQuery('the', sourceReferences: ['linear:workspace']), searchContext())->total)->toBe(1);
});

it('offers identifier-like input to identity resolution before scoring it', function (): void {
    expect((new SearchQuery('MME-1887'))->identifierCandidate())->toBe('MME-1887')
        ->and((new SearchQuery('9f1c2ab3d4e5f60718293a4b5c6d7e8f90a1b2c3'))->identifierCandidate())->toBe('9f1c2ab3d4e5f60718293a4b5c6d7e8f90a1b2c3')
        ->and((new SearchQuery('funes:entity/01J'))->identifierCandidate())->toBe('funes:entity/01J')
        ->and((new SearchQuery('losing the instant'))->identifierCandidate())->toBeNull();
});

it('reports the identifier candidate on the results themselves', function (): void {
    seedSpine();

    $results = app(FullTextSearch::class)->search(new SearchQuery('MME-1887'), searchContext());

    expect($results->identifierCandidate)->toBe('MME-1887')
        ->and($results->truncated)->toBeFalse();
});

it('requires every term to match', function (): void {
    seedSpine();

    $search = app(FullTextSearch::class);

    expect($search->search(new SearchQuery('instant'), searchContext())->total)->toBe(1)
        ->and($search->search(new SearchQuery('instant peristaltic'), searchContext())->total)->toBe(0);
});

it('matches a term at a token boundary rather than anywhere inside a word', function (): void {
    seedSpine();

    $search = app(FullTextSearch::class);

    expect($search->search(new SearchQuery('report'), searchContext())->total)->toBe(1)
        ->and($search->search(new SearchQuery('ported'), searchContext())->total)->toBe(0);
});

it('ranks a denser field above a longer one and breaks ties deterministically', function (): void {
    $store = app(HistoricalAssertionStore::class);
    $context = searchContext();

    $store->append(searchClaim(
        'assert:short',
        searchCommit(),
        'message',
        'Precision audit',
        recordedAt: '2026-08-31T10:00:00Z',
    ), $context);

    $store->append(searchClaim(
        'assert:long',
        searchTicket(),
        'summary',
        'A precision audit is one of the many things this long and rambling description happens to mention along the way',
        recordedAt: '2026-08-31T11:00:00Z',
    ), $context);

    app(FullTextSearch::class)->rebuild();

    $hits = app(FullTextSearch::class)->search(new SearchQuery('precision audit'), $context)->hits;

    expect($hits)->toHaveCount(2)
        ->and($hits[0]->assertion->stableIdentity())->toBe('assert:short')
        ->and($hits[0]->score)->toBeGreaterThan($hits[1]->score);
});

it('pages without dropping or repeating a hit', function (): void {
    $store = app(HistoricalAssertionStore::class);
    $context = searchContext();

    foreach (range(1, 5) as $index) {
        $store->append(searchClaim(
            "assert:page-{$index}",
            searchInput("input:{$index}"),
            'text',
            'A repeated searchable rendering',
            recordedAt: '2026-08-31T12:00:00Z',
        ), $context);
    }

    $search = app(FullTextSearch::class);
    $search->rebuild();

    $first = $search->search(new SearchQuery('repeated searchable', limit: 2), $context);
    $second = $search->search(new SearchQuery('repeated searchable', limit: 2, offset: 2), $context);
    $third = $search->search(new SearchQuery('repeated searchable', limit: 2, offset: 4), $context);

    $identities = array_map(
        fn ($hit): string => $hit->assertion->stableIdentity(),
        [...$first->hits, ...$second->hits, ...$third->hits],
    );

    expect($first->total)->toBe(5)
        ->and($identities)->toBe(['assert:page-1', 'assert:page-2', 'assert:page-3', 'assert:page-4', 'assert:page-5']);
});

it('drops a withdrawn claim from search without waiting for a rebuild', function (): void {
    seedSpine();
    $context = searchContext();

    app(HistoricalAssertionStore::class)->tombstone('assert:commit-message', $context, 'Recorded against the wrong commit.');

    expect(app(FullTextSearch::class)->search(new SearchQuery('losing the instant'), $context)->total)->toBe(0);
});

it('rebuilds identical results from history after the index is destroyed', function (): void {
    seedSpine();
    $context = searchContext();
    $search = app(FullTextSearch::class);

    $before = $search->search(new SearchQuery('peristaltic'), $context);
    $rows = DB::table('funes_text_search_index')->count();

    DB::table('funes_text_search_index')->delete();

    expect($search->search(new SearchQuery('peristaltic'), $context)->total)->toBe(0)
        ->and(DB::table('funes_historical_assertions')->count())->toBe(4)
        ->and($search->rebuild())->toBe($rows);

    $after = $search->search(new SearchQuery('peristaltic'), $context);

    expect($after->toArray())->toBe($before->toArray());
});

it('leaves canonical history untouched when the index is rebuilt', function (): void {
    seedSpine();
    $context = searchContext();

    $before = app(HistoricalAssertionStore::class)->get('assert:commit-message', $context)?->toArray();

    app(FullTextSearch::class)->rebuild();
    app(FullTextSearch::class)->rebuild();

    expect(app(HistoricalAssertionStore::class)->get('assert:commit-message', $context)?->toArray())->toBe($before)
        ->and(DB::table('funes_historical_assertions')->count())->toBe(4);
});

it('rejects a query with no searchable term', function (): void {
    new SearchQuery('   ***   ');
})->throws(InvalidArgumentException::class);

it('rejects a page size outside the documented bounds', function (): void {
    new SearchQuery('anything', limit: 0);
})->throws(InvalidArgumentException::class);
