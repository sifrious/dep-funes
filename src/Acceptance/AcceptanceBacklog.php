<?php

declare(strict_types=1);

namespace Sifrious\Funes\Acceptance;

use Sifrious\Funes\Value\Observation;

interface AcceptanceBacklog
{
    /**
     * Observations persisted before the acceptance boundary existed, and so
     * holding no idempotency key that could prove how they were accepted.
     *
     * @return list<Observation>
     */
    public function unkeyed(int $limit = 100): array;

    public function unkeyedCount(): int;
}
