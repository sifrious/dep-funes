<?php

declare(strict_types=1);

namespace Sifrious\Funes\Assertion;

/**
 * The outcome of appending one assertion: what the store did, and the assertion of
 * record afterwards.
 *
 * On a duplicate the stored assertion is returned rather than the submitted one, so a
 * caller retrying an append learns the identity the store already holds for the claim.
 */
final readonly class AcceptedAssertion
{
    public function __construct(
        public AssertionDisposition $disposition,
        public AbstractHistoricalAssertion $assertion,
    ) {}
}
