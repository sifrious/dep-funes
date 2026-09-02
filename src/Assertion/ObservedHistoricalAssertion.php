<?php

declare(strict_types=1);

namespace Sifrious\Funes\Assertion;

use Sifrious\Funes\Graph\AssertionType;

/**
 * A claim the history store saw at its source.
 *
 * This is the ordinary case: an integration read the source material and recorded
 * what it showed. Evidence is optional, because the source material itself — reachable
 * through the source locator — is the evidence.
 *
 * An observation is not an interpretation. If a process reasoned its way to the claim
 * rather than reading it, the claim is an {@see InferredHistoricalAssertion}, and the
 * two cannot be exchanged by changing a value.
 */
final readonly class ObservedHistoricalAssertion extends AbstractHistoricalAssertion
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
