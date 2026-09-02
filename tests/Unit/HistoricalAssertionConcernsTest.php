<?php

declare(strict_types=1);

use Sifrious\AuthorizationContract\TenantScope;
use Sifrious\Funes\Assertion\AbstractHistoricalAssertion;
use Sifrious\Funes\Assertion\HistoricalAssertionContract;
use Sifrious\Funes\Concern\HasEvidence;
use Sifrious\Funes\Concern\HasProvenance;
use Sifrious\Funes\Concern\HasStableIdentity;
use Sifrious\Funes\Concern\HasTemporalCoordinates;
use Sifrious\Funes\Concern\HasTenantScope;
use Sifrious\Funes\Concern\SerializesTemporalCoordinates;
use Sifrious\Funes\Tests\Fixtures\Assertion\FixtureObservedAssertion;
use Sifrious\Funes\Value\SourceLocator;
use Sifrious\ReferenceContract\CrossPackageReference;

/** The five concerns the design source assigns to HistoricalAssertion. */
function composedConcerns(): array
{
    return [
        HasStableIdentity::class,
        HasProvenance::class,
        HasTemporalCoordinates::class,
        HasTenantScope::class,
        HasEvidence::class,
    ];
}

it('composes every concern the design source assigns to a historical assertion', function (string $concern): void {
    expect(is_subclass_of(HistoricalAssertionContract::class, $concern))->toBeTrue()
        ->and(concernAssertion())->toBeInstanceOf($concern);
})->with(composedConcerns());

it('satisfies stable identity with an opaque value', function (): void {
    /** @var HasStableIdentity $assertion */
    $assertion = concernAssertion();

    expect($assertion->stableIdentity())->toBe('assertion:1')
        ->and($assertion->stableIdentity())->not->toContain(' ');
});

it('satisfies provenance with a recoverable source and a nameable carrier', function (): void {
    $provenance = new CrossPackageReference('sifrious/funes', 'provenance', 'provenance:9');
    /** @var HasProvenance $assertion */
    $assertion = concernAssertion(provenance: $provenance);

    expect($assertion->source()->sourceReference)->toBe('github:repository')
        ->and($assertion->source()->resourceReference)->toBe('github:R_kgDOExample')
        ->and($assertion->provenance()?->equals($provenance))->toBeTrue()
        ->and(concernAssertion()->provenance())->toBeNull();
});

it('satisfies temporal coordinates with three distinct moments', function (): void {
    /** @var HasTemporalCoordinates $assertion */
    $assertion = concernAssertion();

    expect($assertion->occurredAt())->toBeLessThan($assertion->observedAt())
        ->and($assertion->observedAt())->toBeLessThan($assertion->recordedAt());
});

it('satisfies tenant scope with a boundary carried on the durable fact', function (): void {
    /** @var HasTenantScope $assertion */
    $assertion = concernAssertion();

    expect($assertion->tenant()->kind)->toBe('organization')
        ->and($assertion->tenant()->tenant?->id)->toBe('org:7');
});

it('satisfies evidence with durable references rather than copied records', function (): void {
    $evidence = new CrossPackageReference('sifrious/aleph', 'observation', 'observation:4');
    /** @var HasEvidence $assertion */
    $assertion = concernAssertion(evidence: [$evidence]);

    expect($assertion->evidence())->toHaveCount(1)
        ->and($assertion->evidence()[0])->toBeInstanceOf(CrossPackageReference::class)
        ->and($assertion->evidence()[0]->equals($evidence))->toBeTrue();
});

it('does not compose a concern the register records as not applicable', function (string $concern): void {
    expect(interface_exists('Sifrious\\Funes\\Concern\\'.$concern))->toBeFalse();
})->with([
    'HasProviderIdentity',
    'HasSourceLocator',
    'HasActor',
    'HasAuthorizationContext',
    'HasEffectiveInterval',
    'HasImmutableVersion',
    'HasContentHash',
    'HasConfidence',
    'HasParent',
    'HasExternalReferences',
]);

it('documents every concern in the manifesto vocabulary', function (string $concern): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/docs/concerns.md'))->toContain('`'.$concern.'`');
})->with([
    'HasStableIdentity',
    'HasProviderIdentity',
    'HasSourceLocator',
    'HasProvenance',
    'HasTemporalCoordinates',
    'HasEffectiveInterval',
    'HasImmutableVersion',
    'HasContentHash',
    'HasTenantScope',
    'HasActor',
    'HasParent',
    'HasEvidence',
    'HasConfidence',
    'HasAuthorizationContext',
    'HasExternalReferences',
]);

it('keeps concerns free of state so composition cannot duplicate what an object owns', function (): void {
    foreach (glob(dirname(__DIR__, 2).'/src/Concern/*.php') as $file) {
        $reflection = new ReflectionClass('Sifrious\\Funes\\Concern\\'.basename($file, '.php'));

        expect($reflection->getProperties())->toBe([]);
    }
});

it('shares temporal mechanics through a trait rather than restating them per object', function (): void {
    expect(class_uses(AbstractHistoricalAssertion::class))
        ->toContain(SerializesTemporalCoordinates::class);
});

it('rejects an unchronological record through the shared temporal concern', function (): void {
    concernAssertion(observedAt: '2026-09-05T12:00:00Z');
})->throws(InvalidArgumentException::class, 'cannot be recorded before it was observed');

it('formats every temporal coordinate with microsecond precision and an explicit offset', function (): void {
    $serialized = concernAssertion()->toArray();

    foreach (['occurred_at', 'observed_at', 'recorded_at'] as $key) {
        expect($serialized[$key])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}[+-]\d{2}:\d{2}$/');
    }
});

/**
 * @param  list<CrossPackageReference>  $evidence
 */
function concernAssertion(
    ?CrossPackageReference $provenance = null,
    array $evidence = [],
    string $observedAt = '2026-08-30T12:00:00Z',
): FixtureObservedAssertion {
    return new FixtureObservedAssertion(
        'assertion:1',
        new CrossPackageReference('sifrious/elwin', 'conversation', 'chat:1'),
        'title',
        'Historian MVP',
        new SourceLocator('github:repository', 'GitHub', 'github:R_kgDOExample'),
        TenantScope::forTenant(
            'organization',
            new CrossPackageReference('sifrious/landing', 'organization', 'org:7'),
        ),
        new DateTimeImmutable('2026-08-29T12:00:00Z'),
        new DateTimeImmutable($observedAt),
        new DateTimeImmutable('2026-08-31T12:00:00Z'),
        $provenance,
        $evidence,
    );
}
