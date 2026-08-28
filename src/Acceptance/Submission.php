<?php

declare(strict_types=1);

namespace Sifrious\Funes\Acceptance;

use DateTimeImmutable;
use InvalidArgumentException;
use Sifrious\Funes\Value\ObservationDraft;

final readonly class Submission
{
    public function __construct(
        public string $idempotencyKey,
        public ObservationDraft $draft,
        public ?DateTimeImmutable $occurredAt = null,
    ) {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('A submission must carry an idempotency key.');
        }
    }
}
