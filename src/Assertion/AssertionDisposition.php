<?php

declare(strict_types=1);

namespace Sifrious\Funes\Assertion;

/**
 * What an append did.
 *
 * There is no `Changed` case. An assertion is immutable, so re-encountering the same
 * claim is always a duplicate and a different claim is always a new assertion.
 */
enum AssertionDisposition: string
{
    case First = 'first';
    case Duplicate = 'duplicate';
}
