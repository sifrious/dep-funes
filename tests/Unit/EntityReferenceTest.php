<?php

declare(strict_types=1);

use Sifrious\Funes\Value\EntityKind;
use Sifrious\Funes\Value\EntityReference;

it('preserves a typed namespaced reference without interpreting its identifier', function (): void {
    $reference = new EntityReference(EntityKind::Repository, 'github:R_kgDOExample');

    expect($reference->toArray())->toBe([
        'kind' => 'repository',
        'id' => 'github:R_kgDOExample',
    ]);
});

it('rejects a landing database id', function (): void {
    expect(fn () => new EntityReference(EntityKind::Project, '42'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a display name without a namespace', function (): void {
    expect(fn () => new EntityReference(EntityKind::Project, 'Landing'))
        ->toThrow(InvalidArgumentException::class);
});
