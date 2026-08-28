<?php

declare(strict_types=1);

use Sifrious\Funes\Reference\CrossPackageReference;

it('has a stable serialized representation that survives queue and event round trips', function (): void {
    $provenance = new CrossPackageReference('sifrious/funes', 'provenance', 'prov_01');
    $reference = new CrossPackageReference('sifrious/aleph', 'observation', 'obs_01', 'sha256:abc', $provenance);

    $serialized = $reference->toArray();

    expect($serialized)->toBe([
        'contract' => 'sifrious.cross-package-reference',
        'contract_version' => 1,
        'owner' => 'sifrious/aleph',
        'type' => 'observation',
        'id' => 'obs_01',
        'object_version' => 'sha256:abc',
        'provenance' => [
            'contract' => 'sifrious.cross-package-reference',
            'contract_version' => 1,
            'owner' => 'sifrious/funes',
            'type' => 'provenance',
            'id' => 'prov_01',
            'object_version' => null,
            'provenance' => null,
        ],
    ]);

    $queued = json_decode(json_encode($reference, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    $replayed = CrossPackageReference::fromArray($queued);

    expect($replayed->equals($reference))->toBeTrue()
        ->and($replayed->key())->toBe($reference->key());
});

it('rejects unsupported contracts and unstable owner or type values', function (): void {
    expect(fn () => new CrossPackageReference('Aleph', 'observation', 'obs_01'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => new CrossPackageReference('sifrious/aleph', 'Observation Record', 'obs_01'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => CrossPackageReference::fromArray([
            'contract' => 'sifrious.cross-package-reference',
            'contract_version' => 2,
        ]))->toThrow(InvalidArgumentException::class);
});
