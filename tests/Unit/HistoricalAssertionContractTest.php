<?php

declare(strict_types=1);

use Sifrious\AuthorizationContract\TenantScope;
use Sifrious\Funes\Assertion\AbstractHistoricalAssertion;
use Sifrious\Funes\Assertion\HistoricalAssertionContract;
use Sifrious\Funes\Graph\AssertionType;
use Sifrious\Funes\Tests\Fixtures\Assertion\FixtureInferredAssertion;
use Sifrious\Funes\Tests\Fixtures\Assertion\FixtureObservedAssertion;
use Sifrious\Funes\Value\SourceLocator;
use Sifrious\ReferenceContract\CrossPackageReference;

function assertionSubject(string $id = 'chat:1'): CrossPackageReference
{
    return new CrossPackageReference('sifrious/elwin', 'conversation', $id);
}

function assertionTenant(): TenantScope
{
    return TenantScope::forTenant('organization', new CrossPackageReference('sifrious/landing', 'organization', 'org:7'));
}

function assertionSource(): SourceLocator
{
    return new SourceLocator('github:repository', 'GitHub', 'github:R_kgDOExample');
}

/**
 * @param  list<CrossPackageReference>  $evidence
 */
function observedAssertion(
    string $id = 'assertion:1',
    string $predicate = 'title',
    mixed $value = 'Historian MVP',
    ?string $occurredAt = '2026-08-29T12:00:00Z',
    string $observedAt = '2026-08-30T12:00:00Z',
    string $recordedAt = '2026-08-31T12:00:00Z',
    ?CrossPackageReference $provenance = null,
    array $evidence = [],
): FixtureObservedAssertion {
    return new FixtureObservedAssertion(
        $id,
        assertionSubject(),
        $predicate,
        $value,
        assertionSource(),
        assertionTenant(),
        $occurredAt === null ? null : new DateTimeImmutable($occurredAt),
        new DateTimeImmutable($observedAt),
        new DateTimeImmutable($recordedAt),
        $provenance,
        $evidence,
    );
}

it('exposes provider-neutral identity, subject, claim, and temporal coordinates', function (): void {
    $assertion = observedAssertion();

    expect($assertion)->toBeInstanceOf(HistoricalAssertionContract::class)
        ->and($assertion)->toBeInstanceOf(AbstractHistoricalAssertion::class)
        ->and($assertion->assertionId())->toBe('assertion:1')
        ->and($assertion->assertionType())->toBe(AssertionType::Observed)
        ->and($assertion->subject()->equals(assertionSubject()))->toBeTrue()
        ->and($assertion->predicate())->toBe('title')
        ->and($assertion->value())->toBe('Historian MVP')
        ->and($assertion->source()->resourceReference)->toBe('github:R_kgDOExample')
        ->and($assertion->occurredAt()?->format(DATE_ATOM))->toBe('2026-08-29T12:00:00+00:00')
        ->and($assertion->observedAt()->format(DATE_ATOM))->toBe('2026-08-30T12:00:00+00:00')
        ->and($assertion->recordedAt()->format(DATE_ATOM))->toBe('2026-08-31T12:00:00+00:00')
        ->and($assertion->tenant()->equals(assertionTenant()))->toBeTrue()
        ->and($assertion->evidence())->toBe([])
        ->and($assertion->provenance())->toBeNull();
});

it('keeps occurrence, observation, and recording distinct rather than collapsing them', function (): void {
    $assertion = observedAssertion();

    expect($assertion->occurredAt())->not->toEqual($assertion->observedAt())
        ->and($assertion->observedAt())->not->toEqual($assertion->recordedAt());
});

it('accepts a claim whose occurrence time is unknown', function (): void {
    expect(observedAssertion(occurredAt: null)->occurredAt())->toBeNull();
});

it('rejects a claim observed before the fact it reports occurred', function (): void {
    observedAssertion(occurredAt: '2026-08-31T12:00:00Z', observedAt: '2026-08-30T12:00:00Z');
})->throws(InvalidArgumentException::class, 'observed before');

it('rejects a claim recorded before it was observed', function (): void {
    observedAssertion(observedAt: '2026-08-31T12:00:00Z', recordedAt: '2026-08-30T12:00:00Z');
})->throws(InvalidArgumentException::class, 'recorded before');

it('rejects assertion ids that are not opaque values', function (mixed $id): void {
    observedAssertion(id: $id);
})->with(['', ' assertion:1', 'assertion 1'])->throws(InvalidArgumentException::class);

it('rejects predicates that are not stable lowercase identifiers', function (string $predicate): void {
    observedAssertion(predicate: $predicate);
})->with(['Title', 'has title', '1title', ''])->throws(InvalidArgumentException::class);

it('rejects values that cannot cross the serialization boundary', function (): void {
    observedAssertion(value: ['nested' => [new stdClass]]);
})->throws(InvalidArgumentException::class, 'JSON-encodable');

it('preserves structured values through the serialization boundary', function (): void {
    $value = ['labels' => ['mvp', 'birding'], 'count' => 2, 'archived' => false, 'closed_at' => null];
    $assertion = observedAssertion(value: $value);

    expect(FixtureObservedAssertion::fromArray($assertion->toArray())->value())->toBe($value);
});

it('round-trips through its serialized form without losing provenance or evidence', function (): void {
    $provenance = new CrossPackageReference('sifrious/funes', 'provenance', 'provenance:9');
    $evidence = [new CrossPackageReference('sifrious/aleph', 'observation', 'observation:4')];
    $assertion = observedAssertion(provenance: $provenance, evidence: $evidence);

    $restored = FixtureObservedAssertion::fromArray($assertion->toArray());

    expect($restored->toArray())->toBe($assertion->toArray())
        ->and($restored->provenance()?->equals($provenance))->toBeTrue()
        ->and($restored->evidence()[0]->equals($evidence[0]))->toBeTrue();
});

it('refuses to decode a serialized assertion of a different type', function (): void {
    $inferred = observedAssertion()->toArray();
    $inferred['assertion_type'] = AssertionType::Inferred->value;

    FixtureObservedAssertion::fromArray($inferred);
})->throws(InvalidArgumentException::class, 'does not match the decoding class');

it('refuses to decode an unsupported contract version', function (): void {
    $serialized = observedAssertion()->toArray();
    $serialized['contract_version'] = 2;

    FixtureObservedAssertion::fromArray($serialized);
})->throws(InvalidArgumentException::class, 'Unsupported historical assertion contract');

it('names its contract and version in the serialized form', function (): void {
    expect(observedAssertion()->toArray())
        ->toHaveKey('contract', 'sifrious.historical-assertion')
        ->toHaveKey('contract_version', 1);
});

it('serializes to the same document it json-encodes', function (): void {
    $assertion = observedAssertion();

    expect($assertion->jsonSerialize())->toBe($assertion->toArray());
});

it('requires evidence for an inferred claim', function (): void {
    new FixtureInferredAssertion(
        'assertion:2',
        assertionSubject(),
        'relates-to',
        'plan:1',
        assertionSource(),
        assertionTenant(),
        null,
        new DateTimeImmutable('2026-08-30T12:00:00Z'),
        new DateTimeImmutable('2026-08-31T12:00:00Z'),
    );
})->throws(InvalidArgumentException::class, 'Inferred historical assertions require supporting evidence');

it('does not require evidence for an observed claim', function (): void {
    expect(observedAssertion()->assertionType())->toBe(AssertionType::Observed);
});

it('fingerprints the durable fact so a re-encounter deduplicates', function (): void {
    $first = observedAssertion(id: 'assertion:1', recordedAt: '2026-08-31T12:00:00Z');
    $again = observedAssertion(id: 'assertion:2', observedAt: '2026-09-02T12:00:00Z', recordedAt: '2026-09-03T12:00:00Z');

    expect($again->fingerprint())->toBe($first->fingerprint());
});

it('fingerprints a different claim differently', function (): void {
    expect(observedAssertion(value: 'Historian MVP')->fingerprint())
        ->not->toBe(observedAssertion(value: 'Historian MVP 2')->fingerprint());
});

it('separates an inference from an observation of the same claim', function (): void {
    $observed = observedAssertion();
    $inferred = new FixtureInferredAssertion(
        $observed->assertionId(),
        assertionSubject(),
        $observed->predicate(),
        $observed->value(),
        assertionSource(),
        assertionTenant(),
        $observed->occurredAt(),
        $observed->observedAt(),
        $observed->recordedAt(),
        null,
        [new CrossPackageReference('sifrious/kilgore', 'interpretation', 'interpretation:1')],
    );

    expect($inferred->fingerprint())->not->toBe($observed->fingerprint());
});

it('separates the same claim held by different tenants', function (): void {
    $other = new FixtureObservedAssertion(
        'assertion:1',
        assertionSubject(),
        'title',
        'Historian MVP',
        assertionSource(),
        TenantScope::forTenant('organization', new CrossPackageReference('sifrious/landing', 'organization', 'org:8')),
        new DateTimeImmutable('2026-08-29T12:00:00Z'),
        new DateTimeImmutable('2026-08-30T12:00:00Z'),
        new DateTimeImmutable('2026-08-31T12:00:00Z'),
    );

    expect($other->fingerprint())->not->toBe(observedAssertion()->fingerprint());
});

it('rejects evidence that is not a durable reference', function (): void {
    observedAssertion(evidence: ['observation:4']);
})->throws(InvalidArgumentException::class, 'cross-package references');

it('does not name a provider anywhere in the contract or base class', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/src/Assertion/HistoricalAssertionContract.php')
        .file_get_contents(dirname(__DIR__, 2).'/src/Assertion/AbstractHistoricalAssertion.php');

    foreach (['Claude', 'Anthropic', 'OpenAi', 'Slack', 'GitHub', 'Eloquent', 'Illuminate'] as $provider) {
        expect($source)->not->toContain($provider);
    }
});
