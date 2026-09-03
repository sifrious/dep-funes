<?php

declare(strict_types=1);

namespace Sifrious\Funes\Search;

use RuntimeException;

/**
 * The search projection could not be built or read from stored history.
 *
 * Search failing is a retrieval problem, never a historical one: the projection is
 * disposable and nothing in this namespace writes a claim, so a failure here leaves
 * canonical history exactly as it was and is answered by rebuilding.
 */
final class SearchIndexUnavailable extends RuntimeException {}
