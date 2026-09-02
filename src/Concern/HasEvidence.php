<?php

declare(strict_types=1);

namespace Sifrious\Funes\Concern;

use Sifrious\ReferenceContract\CrossPackageReference;

/**
 * A historical record that names the material supporting it.
 *
 * Evidence is a list of durable references, never copied records, so support can be
 * traversed without the supporting material being duplicated or rewritten. A derived
 * or inferred record is required to carry evidence; an observed one is not.
 */
interface HasEvidence
{
    /**
     * Durable references to the supporting material.
     *
     * @return list<CrossPackageReference>
     */
    public function evidence(): array;
}
