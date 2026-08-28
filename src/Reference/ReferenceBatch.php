<?php

declare(strict_types=1);

namespace Sifrious\Funes\Reference;

use InvalidArgumentException;

final readonly class ReferenceBatch
{
    /** @var list<CrossPackageReference> */
    public array $references;

    /**
     * @param  iterable<CrossPackageReference>  $references
     */
    public function __construct(iterable $references)
    {
        $unique = [];

        foreach ($references as $reference) {
            $unique[$reference->key()] = $reference;
        }

        if ($unique === []) {
            throw new InvalidArgumentException('Reference batches must not be empty.');
        }

        $this->references = array_values($unique);
    }
}
