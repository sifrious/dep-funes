<?php

declare(strict_types=1);

use Sifrious\AuthorizationContract\TenantScope;
use Sifrious\Funes\Assertion\AbstractHistoricalAssertion;
use Sifrious\Funes\Assertion\DeclaredHistoricalAssertion;
use Sifrious\Funes\Assertion\HistoricalAssertionContract;
use Sifrious\Funes\Assertion\InferredHistoricalAssertion;
use Sifrious\Funes\Assertion\ObservedHistoricalAssertion;
use Sifrious\Funes\Graph\AssertionType;
use Sifrious\Funes\Value\SourceLocator;
use Sifrious\ReferenceContract\CrossPackageReference;

/** Every concrete assertion class, with the type each one fixes. */
function assertionClasses(): array
{
    return [
        [ObservedHistoricalAssertion::class, AssertionType::Observed],
        [DeclaredHistoricalAssertion::class, AssertionType::Declared],
        [InferredHistoricalAssertion::class, AssertionType::Inferred],
    ];
}

/**
 * @param  class-string<AbstractHistoricalAssertion>  $class
 */
function substitutable(string $class, string $id = 'assertion:1'): AbstractHistoricalAssertion
{
    return new $class(
        $id,
        new CrossPackageReference('sifrious/elwin', 'conversation', 'chat:1'),
        'title',
        'Historian MVP',
        new SourceLocator('github:repository', 'GitHub', 'github:R_kgDOExample'),
        TenantScope::forTenant('organization', new CrossPackageReference('sifrious/landing', 'organization', 'org:7')),
        new DateTimeImmutable('2026-08-29T12:00:00Z'),
        new DateTimeImmutable('2026-08-30T12:00:00Z'),
        new DateTimeImmutable('2026-08-31T12:00:00Z'),
        null,
        [new CrossPackageReference('sifrious/aleph', 'observation', 'observation:4')],
    );
}

it('produces the same observable contract behavior from every subclass', function (string $class, AssertionType $type): void {
    $assertion = substitutable($class);

    expect($assertion)->toBeInstanceOf(HistoricalAssertionContract::class)
        ->and($assertion->assertionType())->toBe($type)
        ->and($assertion->stableIdentity())->toBe('assertion:1')
        ->and($assertion->predicate())->toBe('title')
        ->and($assertion->value())->toBe('Historian MVP')
        ->and($assertion->evidence())->toHaveCount(1)
        ->and($assertion->tenant()->kind)->toBe('organization')
        ->and(array_keys($assertion->toArray()))->toBe(array_keys(substitutable(ObservedHistoricalAssertion::class)->toArray()));
})->with(assertionClasses());

it('round-trips every subclass through its own serialized form', function (string $class): void {
    $assertion = substitutable($class);

    expect($class::fromArray($assertion->toArray())->toArray())->toBe($assertion->toArray());
})->with(array_map(fn (array $case): string => $case[0], assertionClasses()));

it('refuses to decode a serialized assertion belonging to a sibling class', function (string $class, AssertionType $type): void {
    $foreign = substitutable(ObservedHistoricalAssertion::class)->toArray();
    $foreign['assertion_type'] = AssertionType::Declared->value;

    if ($type === AssertionType::Declared) {
        $foreign['assertion_type'] = AssertionType::Observed->value;
    }

    expect(fn () => $class::fromArray($foreign))
        ->toThrow(InvalidArgumentException::class, 'does not match the decoding class');
})->with(assertionClasses());

it('fingerprints the same claim differently under each assertion type', function (): void {
    $fingerprints = array_map(
        fn (array $case): string => substitutable($case[0])->fingerprint(),
        assertionClasses(),
    );

    expect(array_unique($fingerprints))->toHaveCount(count($fingerprints));
});

it('fixes the assertion type in the class so no value can change it', function (string $class, AssertionType $type): void {
    $method = new ReflectionMethod($class, 'assertionType');

    expect($method->getDeclaringClass()->getName())->toBe($class)
        ->and($method->invoke(substitutable($class)))->toBe($type);
})->with(assertionClasses());

it('requires evidence only from the inferred subclass', function (string $class, AssertionType $type): void {
    $build = fn (): AbstractHistoricalAssertion => new $class(
        'assertion:1',
        new CrossPackageReference('sifrious/elwin', 'conversation', 'chat:1'),
        'title',
        'Historian MVP',
        new SourceLocator('github:repository', 'GitHub', 'github:R_kgDOExample'),
        TenantScope::unscoped(),
        null,
        new DateTimeImmutable('2026-08-30T12:00:00Z'),
        new DateTimeImmutable('2026-08-31T12:00:00Z'),
    );

    if ($type === AssertionType::Inferred) {
        expect($build)->toThrow(InvalidArgumentException::class, 'require supporting evidence');

        return;
    }

    expect($build()->evidence())->toBe([]);
})->with(assertionClasses());

it('keeps every concrete assertion a direct child of an abstract parent', function (string $class): void {
    $parent = (new ReflectionClass($class))->getParentClass();

    expect($parent)->not->toBeFalse()
        ->and($parent->isAbstract())->toBeTrue();
})->with(array_map(fn (array $case): string => $case[0], assertionClasses()));

it('has no concrete-to-concrete shortcut anywhere in the assertion hierarchy', function (): void {
    foreach (glob(dirname(__DIR__, 2).'/src/Assertion/*.php') as $file) {
        $class = 'Sifrious\\Funes\\Assertion\\'.basename($file, '.php');
        if (! class_exists($class)) {
            continue;
        }

        $parent = (new ReflectionClass($class))->getParentClass();
        if ($parent === false) {
            continue;
        }

        expect($parent->isAbstract())->toBeTrue(
            "{$class} extends the concrete class {$parent->getName()}.",
        );
    }
});

it('keeps provider payloads out of the canonical representation', function (string $class): void {
    $serialized = substitutable($class)->toArray();

    expect(array_keys($serialized))->toBe([
        'contract',
        'contract_version',
        'id',
        'assertion_type',
        'subject',
        'predicate',
        'value',
        'source',
        'tenant',
        'occurred_at',
        'observed_at',
        'recorded_at',
        'provenance',
        'evidence',
    ]);
})->with(array_map(fn (array $case): string => $case[0], assertionClasses()));
