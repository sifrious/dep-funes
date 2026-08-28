<?php

declare(strict_types=1);

namespace Sifrious\Funes\Reference;

final readonly class ReferenceDirectory
{
    /** @var array<string, ReferenceOwnerResolver> */
    private array $resolvers;

    /**
     * @param  iterable<ReferenceOwnerResolver>  $resolvers
     */
    public function __construct(iterable $resolvers)
    {
        $indexed = [];

        foreach ($resolvers as $resolver) {
            if (isset($indexed[$resolver->owner()])) {
                throw new DuplicateReferenceOwner('Only one reference resolver may own a package namespace.');
            }

            $indexed[$resolver->owner()] = $resolver;
        }

        $this->resolvers = $indexed;
    }

    public function resolveBatch(ReferenceBatch $batch, ReferenceAccess $access): ReferenceResolutionSet
    {
        $byOwner = [];

        foreach ($batch->references as $reference) {
            $byOwner[$reference->owner][] = $reference;
        }

        $resolutions = [];

        foreach ($byOwner as $owner => $references) {
            $ownerBatch = new ReferenceBatch($references);
            $resolver = $this->resolvers[$owner] ?? null;

            if ($resolver === null) {
                foreach ($references as $reference) {
                    $resolutions[] = ReferenceResolution::unavailable($reference);
                }

                continue;
            }

            $resolved = $resolver->resolveBatch($ownerBatch, $access);
            $resolved->assertExact($ownerBatch);

            foreach ($references as $reference) {
                $resolutions[] = $resolved->get($reference);
            }
        }

        return new ReferenceResolutionSet($resolutions);
    }
}
