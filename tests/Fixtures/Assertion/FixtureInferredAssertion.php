<?php

declare(strict_types=1);

namespace Sifrious\Funes\Tests\Fixtures\Assertion;

use Sifrious\Funes\Assertion\AbstractHistoricalAssertion;
use Sifrious\Funes\Graph\AssertionType;

/**
 * A test double standing in for an inferred assertion subclass. It exists to prove
 * that the base class enforces the evidence invariant on inference, and to prove
 * that two subclasses of the same base produce identical contract behavior.
 */
final readonly class FixtureInferredAssertion extends AbstractHistoricalAssertion
{
    public function assertionType(): AssertionType
    {
        return AssertionType::Inferred;
    }
}
