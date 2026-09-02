<?php

declare(strict_types=1);

namespace Sifrious\Funes\Assertion;

use InvalidArgumentException;
use Sifrious\Funes\Graph\AssertionType;

/**
 * Rebuilds a stored assertion document as the concrete class its type names.
 *
 * The base class deliberately does not know its subclasses, so the type-to-class map
 * lives here rather than in the hierarchy. Decoding goes through each subclass's own
 * `fromArray()`, which revalidates every invariant, so a document that was corrupted
 * or hand-edited in storage fails on the way out rather than becoming a malformed
 * object in memory.
 */
final readonly class HistoricalAssertionCodec
{
    /** @param array<string, mixed> $document */
    public static function decode(array $document): AbstractHistoricalAssertion
    {
        $type = $document['assertion_type'] ?? null;
        if (! is_string($type)) {
            throw new InvalidArgumentException('A stored historical assertion requires a string assertion type.');
        }

        return match (AssertionType::tryFrom($type)) {
            AssertionType::Observed => ObservedHistoricalAssertion::fromArray($document),
            AssertionType::Declared => DeclaredHistoricalAssertion::fromArray($document),
            AssertionType::Inferred => InferredHistoricalAssertion::fromArray($document),
            null => throw new InvalidArgumentException("Unknown historical assertion type [{$type}]."),
        };
    }
}
