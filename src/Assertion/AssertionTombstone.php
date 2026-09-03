<?php

declare(strict_types=1);

namespace Sifrious\Funes\Assertion;

use DateTimeImmutable;
use Sifrious\AuthorizationContract\AuthorizationContext;

/**
 * An append-only record that an assertion was withdrawn from the live view.
 *
 * The assertion itself is never deleted. A tombstone hides it from retrieval while
 * preserving the claim, who withdrew it, when, and why, so a withdrawal remains
 * auditable. Destroying the underlying material is erasure, not tombstoning.
 */
final readonly class AssertionTombstone
{
    public function __construct(
        public string $assertionId,
        public string $reason,
        public AuthorizationContext $authorization,
        public DateTimeImmutable $tombstonedAt,
    ) {}
}
