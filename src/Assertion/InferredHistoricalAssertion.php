<?php

declare(strict_types=1);

namespace Sifrious\Funes\Assertion;

use Sifrious\Funes\Graph\AssertionType;

/**
 * A claim some later process reasoned its way to.
 *
 * An inference is never an observed fact and must never become indistinguishable from
 * one. The base class requires non-empty evidence for this type, so an inference always
 * names the material it was drawn from and a reader can follow it back.
 */
final readonly class InferredHistoricalAssertion extends AbstractHistoricalAssertion
{
    public function assertionType(): AssertionType
    {
        return AssertionType::Inferred;
    }

    /** @param array<string, mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $state = self::decodeState($serialized, AssertionType::Inferred);

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
