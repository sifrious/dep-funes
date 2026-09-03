<?php

declare(strict_types=1);

namespace Sifrious\Funes\Assertion;

use RuntimeException;

/** One assertion identity was reused for a different claim. */
final class AssertionConflict extends RuntimeException {}
