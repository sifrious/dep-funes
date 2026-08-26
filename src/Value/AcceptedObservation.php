<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

final readonly class AcceptedObservation
{
    public function __construct(
        public Observation $observation,
        public ObservationDisposition $disposition,
    ) {}
}
