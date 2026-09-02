<?php

declare(strict_types=1);

namespace Sifrious\Funes\Tests\Fixtures\Assertion;

use Sifrious\Funes\Assertion\AbstractHistoricalAssertion;
use Sifrious\Funes\Graph\AssertionType;

/**
 * A test double standing in for an observed assertion subclass until the real
 * provider-family and concrete subclasses land. It adds no semantics of its own.
 */
final readonly class FixtureObservedAssertion extends AbstractHistoricalAssertion
{
    public function assertionType(): AssertionType
    {
        return AssertionType::Observed;
    }

    /** @param array<string, mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $state = self::decodeState($serialized, AssertionType::Observed);

        return new self(
            $state['id'],
            $state['subject'],
            $state['predicate'],
            $state['value'],
            $state['source'],
            $state['tenant'],
            $state['occurred_at'],
            $state['observed_at'],
            $state['recorded_at'],
            $state['provenance'],
            $state['evidence'],
        );
    }
}
