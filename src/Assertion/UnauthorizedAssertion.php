<?php

declare(strict_types=1);

namespace Sifrious\Funes\Assertion;

use RuntimeException;

/** A caller acted on evidence outside the tenant its authorization context holds. */
final class UnauthorizedAssertion extends RuntimeException {}
