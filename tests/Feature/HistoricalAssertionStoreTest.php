<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Sifrious\AuthorizationContract\ActorContext;
use Sifrious\AuthorizationContract\ActorKind;
use Sifrious\AuthorizationContract\AuthorizationContext;
use Sifrious\AuthorizationContract\TenantScope;
use Sifrious\Funes\Assertion\AbstractHistoricalAssertion;
use Sifrious\Funes\Assertion\AssertionConflict;
use Sifrious\Funes\Assertion\AssertionDisposition;
use Sifrious\Funes\Assertion\DeclaredHistoricalAssertion;
use Sifrious\Funes\Assertion\HistoricalAssertionStore;
use Sifrious\Funes\Assertion\InferredHistoricalAssertion;
use Sifrious\Funes\Assertion\ObservedHistoricalAssertion;
use Sifrious\Funes\Assertion\UnauthorizedAssertion;
use Sifrious\Funes\Value\SourceLocator;
use Sifrious\ReferenceContract\CrossPackageReference;

uses(RefreshDatabase::class);

function tenantFor(string $organization): TenantScope
{
    return TenantScope::forTenant('organization', new CrossPackageReference('sifrious/landing', 'organization', $organization));
}

function contextFor(string $organization, string $actor = 'user:mary'): AuthorizationContext
{
    return new AuthorizationContext(
        new ActorContext(new CrossPackageReference('sifrious/landing', 'user', $actor), ActorKind::Human),
        tenantFor($organization),
    );
}

function chat(string $id = 'chat:1'): CrossPackageReference
{
    return new CrossPackageReference('sifrious/elwin', 'conversation', $id);
}

/**
 * @param  class-string<AbstractHistoricalAssertion>  $class
 * @param  list<CrossPackageReference>  $evidence
 */
function claim(
    string $id,
    string $predicate = 'title',
    mixed $value = 'Historian MVP',
    string $organization = 'org:7',
    string $recordedAt = '2026-08-31T12:00:00Z',
    string $class = ObservedHistoricalAssertion::class,
    array $evidence = [],
    ?CrossPackageReference $subject = null,
): AbstractHistoricalAssertion {
    return new $class(
        $id,
        $subject ?? chat(),
        $predicate,
        $value,
        new SourceLocator('github:repository', 'GitHub', 'github:R_kgDOExample'),
        tenantFor($organization),
        new DateTimeImmutable('2026-08-29T12:00:00Z'),
        new DateTimeImmutable('2026-08-30T12:00:00Z'),
        new DateTimeImmutable($recordedAt),
        null,
        $evidence,
    );
}

function store(): HistoricalAssertionStore
{
    return app(HistoricalAssertionStore::class);
}

it('resolves the assertion store through the container', function (): void {
    expect(store())->toBeInstanceOf(HistoricalAssertionStore::class);
});

it('survives a persistence and retrieval round trip without semantic loss', function (string $class): void {
    $evidence = $class === InferredHistoricalAssertion::class
        ? [new CrossPackageReference('sifrious/aleph', 'observation', 'observation:4')]
        : [];
    $assertion = claim('assertion:1', class: $class, evidence: $evidence);

    store()->append($assertion, contextFor('org:7'));

    $restored = store()->get('assertion:1', contextFor('org:7'));

    expect($restored)->toBeInstanceOf($class)
        ->and($restored?->toArray())->toBe($assertion->toArray())
        ->and($restored?->fingerprint())->toBe($assertion->fingerprint());
})->with([ObservedHistoricalAssertion::class, DeclaredHistoricalAssertion::class, InferredHistoricalAssertion::class]);

it('accepts a first claim and treats a re-encounter as a duplicate', function (): void {
    $first = store()->append(claim('assertion:1'), contextFor('org:7'));
    $again = store()->append(claim('assertion:2'), contextFor('org:7'));

    expect($first->disposition)->toBe(AssertionDisposition::First)
        ->and($again->disposition)->toBe(AssertionDisposition::Duplicate)
        ->and($again->assertion->stableIdentity())->toBe('assertion:1')
        ->and(store()->forSubject(chat(), contextFor('org:7')))->toHaveCount(1);
});

it('returns the stored assertion on a duplicate so a retry learns the identity of record', function (): void {
    store()->append(claim('assertion:1'), contextFor('org:7'));

    expect(store()->append(claim('assertion:99'), contextFor('org:7'))->assertion->stableIdentity())
        ->toBe('assertion:1');
});

it('refuses to reuse one identity for a different claim', function (): void {
    store()->append(claim('assertion:1', value: 'Historian MVP'), contextFor('org:7'));
    store()->append(claim('assertion:1', value: 'Something else'), contextFor('org:7'));
})->throws(AssertionConflict::class, 'already held by a different claim');

it('keeps the same claim in different tenants as separate facts', function (): void {
    store()->append(claim('assertion:1', organization: 'org:7'), contextFor('org:7'));
    $other = store()->append(claim('assertion:2', organization: 'org:8'), contextFor('org:8'));

    expect($other->disposition)->toBe(AssertionDisposition::First)
        ->and(store()->forSubject(chat(), contextFor('org:7')))->toHaveCount(1)
        ->and(store()->forSubject(chat(), contextFor('org:8')))->toHaveCount(1);
});

it('refuses an append into a tenant the caller does not hold', function (): void {
    store()->append(claim('assertion:1', organization: 'org:8'), contextFor('org:7'));
})->throws(UnauthorizedAssertion::class, 'tenant the caller does not hold');

it('does not reveal another tenant evidence through retrieval', function (): void {
    store()->append(claim('assertion:1', organization: 'org:8'), contextFor('org:8'));

    expect(store()->get('assertion:1', contextFor('org:7')))->toBeNull()
        ->and(store()->forSubject(chat(), contextFor('org:7')))->toBe([])
        ->and(store()->tombstoneOf('assertion:1', contextFor('org:7')))->toBeNull();
});

it('returns a subject timeline most recently recorded first', function (): void {
    store()->append(claim('assertion:1', predicate: 'title', recordedAt: '2026-08-31T12:00:00Z'), contextFor('org:7'));
    store()->append(claim('assertion:2', predicate: 'status', value: 'open', recordedAt: '2026-09-01T12:00:00Z'), contextFor('org:7'));

    expect(array_map(fn ($a) => $a->stableIdentity(), store()->forSubject(chat(), contextFor('org:7'))))
        ->toBe(['assertion:2', 'assertion:1']);
});

it('filters a subject timeline by predicate', function (): void {
    store()->append(claim('assertion:1', predicate: 'title'), contextFor('org:7'));
    store()->append(claim('assertion:2', predicate: 'status', value: 'open'), contextFor('org:7'));

    expect(array_map(fn ($a) => $a->stableIdentity(), store()->forSubject(chat(), contextFor('org:7'), 'status')))
        ->toBe(['assertion:2']);
});

it('separates subjects', function (): void {
    store()->append(claim('assertion:1'), contextFor('org:7'));
    store()->append(claim('assertion:2', value: 'Other', subject: chat('chat:2')), contextFor('org:7'));

    expect(store()->forSubject(chat('chat:2'), contextFor('org:7')))->toHaveCount(1);
});

it('reproduces what the store knew at a past moment', function (): void {
    store()->append(claim('assertion:1', value: 'First name', recordedAt: '2026-08-31T12:00:00Z'), contextFor('org:7'));
    store()->append(claim('assertion:2', value: 'Corrected name', recordedAt: '2026-09-02T12:00:00Z'), contextFor('org:7'));

    $before = store()->asOf(chat(), 'title', new DateTimeImmutable('2026-09-01T12:00:00Z'), contextFor('org:7'));
    $after = store()->asOf(chat(), 'title', new DateTimeImmutable('2026-09-03T12:00:00Z'), contextFor('org:7'));

    expect($before?->value())->toBe('First name')
        ->and($after?->value())->toBe('Corrected name');
});

it('knows nothing before the first assertion was recorded', function (): void {
    store()->append(claim('assertion:1', recordedAt: '2026-08-31T12:00:00Z'), contextFor('org:7'));

    expect(store()->asOf(chat(), 'title', new DateTimeImmutable('2026-08-30T12:00:00Z'), contextFor('org:7')))
        ->toBeNull();
});

it('scopes a point-in-time query to the caller tenant', function (): void {
    store()->append(claim('assertion:1', organization: 'org:8'), contextFor('org:8'));

    expect(store()->asOf(chat(), 'title', new DateTimeImmutable('2026-09-03T12:00:00Z'), contextFor('org:7')))
        ->toBeNull();
});

it('withdraws an assertion from the live view without destroying it', function (): void {
    store()->append(claim('assertion:1'), contextFor('org:7'));
    $tombstone = store()->tombstone('assertion:1', contextFor('org:7'), 'Source retracted the field.');

    expect(store()->get('assertion:1', contextFor('org:7')))->toBeNull()
        ->and(store()->forSubject(chat(), contextFor('org:7')))->toBe([])
        ->and($tombstone->reason)->toBe('Source retracted the field.')
        ->and(DB::table('funes_historical_assertions')->where('id', 'assertion:1')->exists())->toBeTrue();
});

it('keeps a withdrawal auditable', function (): void {
    store()->append(claim('assertion:1'), contextFor('org:7'));
    store()->tombstone('assertion:1', contextFor('org:7'), 'Source retracted the field.');

    $record = store()->tombstoneOf('assertion:1', contextFor('org:7'));

    expect($record?->assertionId)->toBe('assertion:1')
        ->and($record?->reason)->toBe('Source retracted the field.')
        ->and($record?->authorization->tenant->equals(tenantFor('org:7')))->toBeTrue()
        ->and($record?->authorization->actor->toArray())->toBe(contextFor('org:7')->actor->toArray());
});

it('preserves the original withdrawal when a tombstone is repeated', function (): void {
    store()->append(claim('assertion:1'), contextFor('org:7'));
    $first = store()->tombstone('assertion:1', contextFor('org:7'), 'Source retracted the field.');
    $again = store()->tombstone('assertion:1', contextFor('org:7'), 'A different reason.');

    expect($again->reason)->toBe($first->reason)
        ->and($again->tombstonedAt)->toEqual($first->tombstonedAt)
        ->and(DB::table('funes_assertion_tombstones')->count())->toBe(1);
});

it('still reports a withdrawn claim as known before the withdrawal', function (): void {
    store()->append(claim('assertion:1', recordedAt: '2026-08-31T12:00:00Z'), contextFor('org:7'));
    store()->tombstone('assertion:1', contextFor('org:7'), 'Source retracted the field.');

    $before = store()->asOf(chat(), 'title', new DateTimeImmutable('2026-09-01T12:00:00Z'), contextFor('org:7'));
    $now = store()->asOf(chat(), 'title', new DateTimeImmutable('2030-01-01T12:00:00Z'), contextFor('org:7'));

    expect($before?->stableIdentity())->toBe('assertion:1')
        ->and($now)->toBeNull();
});

it('refuses to withdraw another tenant assertion', function (): void {
    store()->append(claim('assertion:1', organization: 'org:8'), contextFor('org:8'));
    store()->tombstone('assertion:1', contextFor('org:7'), 'Not mine to withdraw.');
})->throws(UnauthorizedAssertion::class, 'not available to this tenant');

it('refuses to withdraw an assertion that does not exist', function (): void {
    store()->tombstone('assertion:missing', contextFor('org:7'), 'Nothing here.');
})->throws(UnauthorizedAssertion::class);

it('requires a reason for a withdrawal', function (): void {
    store()->append(claim('assertion:1'), contextFor('org:7'));
    store()->tombstone('assertion:1', contextFor('org:7'), '   ');
})->throws(InvalidArgumentException::class, 'requires a reason');

it('reports no tombstone for a live assertion', function (): void {
    store()->append(claim('assertion:1'), contextFor('org:7'));

    expect(store()->tombstoneOf('assertion:1', contextFor('org:7')))->toBeNull();
});
