<?php

declare(strict_types=1);

namespace Sifrious\Funes\Assertion;

use Sifrious\Funes\Graph\AssertionType;

/**
 * A claim the source itself stated rather than merely exhibited.
 *
 * A declaration carries the source's own word for something — a field it populated, a
 * label it applied, a relationship it named. It ranks above an observation of adjacency
 * or coincidence and below an inference, and it stays distinguishable from both so a
 * later reader can tell what the source actually committed to.
 */
final readonly class DeclaredHistoricalAssertion extends AbstractHistoricalAssertion
{
    public function assertionType(): AssertionType
    {
        return AssertionType::Declared;
    }

    /** @param array<string, mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $state = self::decodeState($serialized, AssertionType::Declared);

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
