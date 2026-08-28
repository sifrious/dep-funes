<?php

declare(strict_types=1);

use Sifrious\Funes\Reference\CrossPackageReference;
use Sifrious\Funes\Reference\IncompleteReferenceResolution;
use Sifrious\Funes\Reference\ReferenceAccess;
use Sifrious\Funes\Reference\ReferenceBatch;
use Sifrious\Funes\Reference\ReferenceDirectory;
use Sifrious\Funes\Reference\ReferenceOwnerResolver;
use Sifrious\Funes\Reference\ReferenceResolution;
use Sifrious\Funes\Reference\ReferenceResolutionSet;
use Sifrious\Funes\Reference\ReferenceResolutionStatus;
use Sifrious\Funes\Reference\ReferenceSnapshot;

function referenceOwner(string $owner, Closure $resolve): ReferenceOwnerResolver
{
    return new class($owner, $resolve) implements ReferenceOwnerResolver
    {
        public int $calls = 0;

        public function __construct(
            private readonly string $package,
            private readonly Closure $resolve,
        ) {}

        public function owner(): string
        {
            return $this->package;
        }

        public function resolveBatch(ReferenceBatch $batch, ReferenceAccess $access): ReferenceResolutionSet
        {
            $this->calls++;

            return new ReferenceResolutionSet(($this->resolve)($batch, $access));
        }
    };
}

it('resolves two Landing package workflows once per owner without model imports or table access', function (): void {
    $aleph = referenceOwner('sifrious/aleph', function (ReferenceBatch $batch, ReferenceAccess $access): array {
        return array_map(
            fn (CrossPackageReference $reference): ReferenceResolution => ReferenceResolution::available(
                new ReferenceSnapshot($reference, 'GitHub observation', ['authorized_for' => $access->principal->id]),
            ),
            $batch->references,
        );
    });

    $kilgore = referenceOwner('sifrious/kilgore', function (ReferenceBatch $batch, ReferenceAccess $access): array {
        return array_map(
            fn (CrossPackageReference $reference): ReferenceResolution => ($access->claims['project'] ?? null) === 'landing'
                ? ReferenceResolution::available(new ReferenceSnapshot($reference, 'Evidence-backed interpretation'))
                : ReferenceResolution::unauthorized($reference),
            $batch->references,
        );
    });

    $alephObservation = new CrossPackageReference(
        'sifrious/aleph',
        'observation',
        'obs_01',
        provenance: new CrossPackageReference('sifrious/funes', 'provenance', 'prov_01'),
    );
    $alephArtifact = new CrossPackageReference('sifrious/aleph', 'artifact', 'artifact_01');
    $kilgoreInterpretation = new CrossPackageReference('sifrious/kilgore', 'interpretation', 'interpretation_01');
    $principal = new CrossPackageReference('sifrious/accounts', 'user', 'user_01');
    $batch = new ReferenceBatch([$alephObservation, $alephArtifact, $kilgoreInterpretation]);
    $resolved = (new ReferenceDirectory([$aleph, $kilgore]))->resolveBatch(
        $batch,
        new ReferenceAccess($principal, ['project' => 'landing']),
    );

    expect($resolved->get($alephObservation)->status)->toBe(ReferenceResolutionStatus::Available)
        ->and($resolved->get($alephObservation)->reference->provenance?->id)->toBe('prov_01')
        ->and($resolved->get($kilgoreInterpretation)->status)->toBe(ReferenceResolutionStatus::Available)
        ->and($aleph->calls)->toBe(1)
        ->and($kilgore->calls)->toBe(1);
});

it('keeps identity and display snapshots meaningful through current-state changes', function (): void {
    $current = new CrossPackageReference('sifrious/aleph', 'source', 'source_current');
    $deleted = new CrossPackageReference('sifrious/aleph', 'source', 'source_deleted', 'v1');
    $old = new CrossPackageReference('sifrious/kilgore', 'interpretation', 'interpretation_old', 'v1');
    $replacement = new CrossPackageReference('sifrious/kilgore', 'interpretation', 'interpretation_new', 'v2');
    $snapshot = new ReferenceSnapshot($deleted, 'Deleted source as observed');

    $resolver = referenceOwner('sifrious/aleph', fn (ReferenceBatch $batch): array => array_map(
        fn (CrossPackageReference $reference): ReferenceResolution => $reference->equals($deleted)
            ? ReferenceResolution::tombstoned($reference, $snapshot)
            : ReferenceResolution::available(new ReferenceSnapshot($reference, 'Current source name')),
        $batch->references,
    ));
    $kilgore = referenceOwner('sifrious/kilgore', fn (ReferenceBatch $batch): array => array_map(
        fn (CrossPackageReference $reference): ReferenceResolution => ReferenceResolution::superseded(
            $reference,
            $replacement,
            new ReferenceSnapshot($reference, 'Original interpretation'),
        ),
        $batch->references,
    ));

    $resolved = (new ReferenceDirectory([$resolver, $kilgore]))->resolveBatch(
        new ReferenceBatch([$current, $deleted, $old]),
        new ReferenceAccess(new CrossPackageReference('sifrious/accounts', 'user', 'user_01')),
    );

    expect($resolved->get($deleted)->status)->toBe(ReferenceResolutionStatus::Tombstoned)
        ->and($resolved->get($deleted)->snapshot?->label)->toBe('Deleted source as observed')
        ->and($resolved->get($old)->status)->toBe(ReferenceResolutionStatus::Superseded)
        ->and($resolved->get($old)->supersededBy?->equals($replacement))->toBeTrue()
        ->and($old->id)->toBe('interpretation_old');
});

it('makes unavailable unauthorized and incomplete resolutions explicit', function (): void {
    $unknown = new CrossPackageReference('sifrious/titan', 'work-item', 'work_01');
    $secret = new CrossPackageReference('sifrious/kilgore', 'interpretation', 'secret_01');
    $principal = new CrossPackageReference('sifrious/accounts', 'user', 'user_01');
    $kilgore = referenceOwner('sifrious/kilgore', fn (ReferenceBatch $batch): array => array_map(
        fn (CrossPackageReference $reference): ReferenceResolution => ReferenceResolution::unauthorized($reference),
        $batch->references,
    ));
    $directory = new ReferenceDirectory([$kilgore]);
    $resolved = $directory->resolveBatch(new ReferenceBatch([$unknown, $secret]), new ReferenceAccess($principal));

    expect($resolved->get($unknown)->status)->toBe(ReferenceResolutionStatus::Unavailable)
        ->and($resolved->get($secret)->status)->toBe(ReferenceResolutionStatus::Unauthorized)
        ->and($resolved->get($secret)->snapshot)->toBeNull();

    $incomplete = referenceOwner('sifrious/aleph', fn (): array => []);

    expect(fn () => (new ReferenceDirectory([$incomplete]))->resolveBatch(
        new ReferenceBatch([new CrossPackageReference('sifrious/aleph', 'observation', 'obs_01')]),
        new ReferenceAccess($principal),
    ))->toThrow(IncompleteReferenceResolution::class);
});
