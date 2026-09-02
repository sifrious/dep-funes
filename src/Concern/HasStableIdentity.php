<?php

declare(strict_types=1);

namespace Sifrious\Funes\Concern;

/**
 * A historical record identified by a stable, opaque value that survives
 * re-ingestion, projection rebuilds, and export.
 *
 * The identity is opaque on purpose. It never encodes a provider, a host database
 * key, or a display name, so no consumer can parse meaning out of it.
 */
interface HasStableIdentity
{
    /** The stable, opaque identity of this record. */
    public function stableIdentity(): string;
}
