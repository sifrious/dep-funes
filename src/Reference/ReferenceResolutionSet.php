<?php

declare(strict_types=1);

namespace Sifrious\Funes\Reference;

use InvalidArgumentException;
use Sifrious\ReferenceContract\CrossPackageReference;

final readonly class ReferenceResolutionSet
{
    /** @var array<string, ReferenceResolution> */
    private array $resolutions;

    /**
     * @param  iterable<ReferenceResolution>  $resolutions
     */
    public function __construct(iterable $resolutions)
    {
        $indexed = [];

        foreach ($resolutions as $resolution) {
            $key = $resolution->reference->key();

            if (isset($indexed[$key])) {
                throw new InvalidArgumentException('Reference resolution sets cannot contain duplicate references.');
            }

            $indexed[$key] = $resolution;
        }

        $this->resolutions = $indexed;
    }

    public function get(CrossPackageReference $reference): ReferenceResolution
    {
        return $this->resolutions[$reference->key()]
            ?? throw new IncompleteReferenceResolution('The owning package did not resolve every requested reference.');
    }

    /**
     * @return list<ReferenceResolution>
     */
    public function all(): array
    {
        return array_values($this->resolutions);
    }

    public function assertExact(ReferenceBatch $batch): void
    {
        if (count($this->resolutions) !== count($batch->references)) {
            throw new IncompleteReferenceResolution('The owning package returned an incomplete or unexpected resolution set.');
        }

        foreach ($batch->references as $reference) {
            $this->get($reference);
        }
    }
}
