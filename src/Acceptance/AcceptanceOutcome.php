<?php

declare(strict_types=1);

namespace Sifrious\Funes\Acceptance;

enum AcceptanceOutcome: string
{
    case Accepted = 'accepted';
    case Replayed = 'replayed';
    case Rejected = 'rejected';
    case InFlight = 'in_flight';

    public function isAuthoritative(): bool
    {
        return $this === self::Accepted || $this === self::Replayed;
    }

    public function createdHistory(): bool
    {
        return $this === self::Accepted;
    }
}
