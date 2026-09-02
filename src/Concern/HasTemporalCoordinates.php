<?php

declare(strict_types=1);

namespace Sifrious\Funes\Concern;

use DateTimeImmutable;

/**
 * A historical record that keeps occurrence, observation, and recording distinct.
 *
 * A fact may hold on Monday, be observed during a synchronization on Wednesday, and
 * enter this history store on Thursday. Collapsing those three moments into one
 * timestamp makes historical reconstruction unreliable, so they stay separate and
 * must be chronological. Occurrence may be unknown; the other two never are.
 */
interface HasTemporalCoordinates
{
    /** When the reported fact held, when the source reports it. */
    public function occurredAt(): ?DateTimeImmutable;

    /** When the record was observed at the source. */
    public function observedAt(): DateTimeImmutable;

    /** When the record entered this history store. */
    public function recordedAt(): DateTimeImmutable;
}
