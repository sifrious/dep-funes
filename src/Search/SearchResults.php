<?php

declare(strict_types=1);

namespace Sifrious\Funes\Search;

/**
 * One page of hits, the authorized total behind it, and the identifier the caller
 * should have resolved first.
 *
 * The total counts only what this caller's tenant may see. It is computed after the
 * authorization filter, never before, so a count can never report the existence of
 * history the caller cannot read.
 *
 * `truncated` says the total exceeded the window the engine ranked. Ranking a bounded
 * window keeps a broad query from loading a corpus into memory, and saying so keeps
 * the result honest about the difference between what matched and what was scored.
 */
final readonly class SearchResults
{
    /**
     * @param  list<SearchHit>  $hits
     */
    public function __construct(
        public array $hits,
        public int $total,
        public bool $truncated = false,
        public ?string $identifierCandidate = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->hits === [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'truncated' => $this->truncated,
            'identifier_candidate' => $this->identifierCandidate,
            'hits' => array_map(fn (SearchHit $hit): array => $hit->toArray(), $this->hits),
        ];
    }
}
