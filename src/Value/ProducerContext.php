<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

final readonly class ProducerContext
{
    public function __construct(
        public Producer $producer,
        public IngestionRun $ingestionRun,
    ) {}
}
