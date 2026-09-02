<?php

declare(strict_types=1);

namespace Sifrious\Funes\Concern;

use Sifrious\Funes\Value\SourceLocator;
use Sifrious\ReferenceContract\CrossPackageReference;

/**
 * A historical record that remains addressable back to the material it came from.
 *
 * The source locator is what the owning integration needs to recover the original
 * material; the provenance reference names the assertion that carried this record
 * into the history store. Both together answer "where did this come from?" without
 * a consumer knowing the source system.
 */
interface HasProvenance
{
    /** Where the record came from, in terms the owning source can resolve. */
    public function source(): SourceLocator;

    /** The provenance assertion that carried this record, when one is recorded. */
    public function provenance(): ?CrossPackageReference;
}
