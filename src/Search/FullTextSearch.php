<?php

declare(strict_types=1);

namespace Sifrious\Funes\Search;

use Sifrious\AuthorizationContract\AuthorizationContext;

/**
 * Discovery of historical assertions by their text.
 *
 * This is the fallback path after deterministic identity resolution. Someone who
 * knows an identifier should resolve it; someone who only remembers a phrase from a
 * commit message, a ticket title, or their own earlier input searches for it.
 *
 * The index behind this seam is a projection and never an authority. It is rebuilt
 * in full from stored assertions, so losing it costs a rebuild and no history, and
 * nothing in this contract can write a historical claim.
 *
 * Every read takes the caller's authorization context and is scoped to that context's
 * tenant before anything is scored or counted. A tenant's history is absent from
 * another tenant's results and from its totals.
 */
interface FullTextSearch
{
    /**
     * Rebuild the whole index from stored assertions and return the rows written.
     *
     * Rebuilding is deterministic: the same history produces the same index, and the
     * same query then produces the same hits in the same order.
     */
    public function rebuild(): int;

    /** The authorized page of hits for one query. */
    public function search(SearchQuery $query, AuthorizationContext $authorization): SearchResults;
}
