<?php

declare(strict_types=1);

namespace Sifrious\Funes\Search;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use JsonException;
use Sifrious\AuthorizationContract\AuthorizationContext;
use Sifrious\AuthorizationContract\TenantScope;
use Sifrious\Funes\Assertion\AbstractHistoricalAssertion;
use Sifrious\Funes\Assertion\HistoricalAssertionCodec;
use Sifrious\Funes\Time\StoredTimestamp;
use stdClass;

/**
 * A full-text index kept in an ordinary table, rebuilt from stored assertions.
 *
 * Matching is a portable `LIKE` over normalized text rather than a vendor full-text
 * engine, and scoring happens here rather than in SQL. That is a deliberate trade:
 * it costs relevance sophistication and buys one behavior that is identical on every
 * driver this package runs on, which is what makes a documented ranking testable.
 * The seam above is an interface, so a corpus that outgrows this swaps the engine
 * without moving the contract.
 *
 * Two properties are load-bearing. The tenant filter is applied in SQL before any row
 * is fetched, scored, or counted, so nothing about another tenant's history reaches
 * the ranking stage. And tombstones are excluded by joining live history at query
 * time rather than at rebuild time, so withdrawing a claim hides it from search
 * immediately instead of at the next rebuild.
 */
final readonly class SqlFullTextSearch implements FullTextSearch
{
    /**
     * How many matching rows are ranked. Beyond this the total still counts every
     * authorized match; only the scored window is bounded, and the result says so.
     */
    private const RANKED_WINDOW = 1000;

    /** Rows per insert while rebuilding. */
    private const INSERT_CHUNK = 200;

    /** Characters of surrounding text a snippet carries. */
    private const SNIPPET_LENGTH = 160;

    /** Characters of lead-in before the matched term. */
    private const SNIPPET_LEAD = 40;

    public function __construct(private ConnectionInterface $connection) {}

    public function rebuild(): int
    {
        return $this->connection->transaction(function (): int {
            $this->connection->table('funes_text_search_index')->delete();

            $rows = [];

            foreach ($this->connection->table('funes_historical_assertions')->orderBy('id')->cursor() as $stored) {
                $assertion = $this->hydrate($stored);

                foreach (SearchText::fields($assertion) as $field => $content) {
                    $rows[] = [
                        'assertion_id' => $assertion->stableIdentity(),
                        'tenant_key' => (string) $stored->tenant_key,
                        'subject_type' => $assertion->subject()->type,
                        'predicate' => $assertion->predicate(),
                        'source_reference' => $assertion->source()->sourceReference,
                        'field' => $field,
                        'content' => $content,
                        // Padded so a `LIKE` pattern can anchor on a term boundary.
                        'content_normalized' => ' '.SearchText::normalize($content).' ',
                        'recorded_at' => StoredTimestamp::format($assertion->recordedAt()),
                    ];
                }
            }

            foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
                $this->connection->table('funes_text_search_index')->insert($chunk);
            }

            return count($rows);
        }, 3);
    }

    public function search(SearchQuery $query, AuthorizationContext $authorization): SearchResults
    {
        $tenantKey = self::tenantKey($authorization->tenant);

        $total = $this->matches($query, $tenantKey)->count();

        $rows = array_values($this->matches($query, $tenantKey)
            ->orderByDesc('search.recorded_at')
            ->orderBy('search.assertion_id')
            ->orderBy('search.field')
            ->limit(self::RANKED_WINDOW)
            ->get(['search.assertion_id', 'search.field', 'search.content', 'search.recorded_at'])
            ->all());

        $hits = $this->rank($query, $rows);

        return new SearchResults(
            array_slice($hits, $query->offset, $query->limit),
            $total,
            $total > self::RANKED_WINDOW,
            $query->identifierCandidate(),
        );
    }

    /**
     * Every authorized, live index row satisfying the query's terms and filters.
     *
     * A term matches at a token boundary: `run` finds "run" and "running" but not
     * "prerun". Terms carry no wildcards — normalization has already discarded every
     * character `LIKE` would treat as one.
     */
    private function matches(SearchQuery $query, string $tenantKey): Builder
    {
        $builder = $this->connection->table('funes_text_search_index as search')
            ->leftJoin('funes_assertion_tombstones as tombstones', 'tombstones.assertion_id', '=', 'search.assertion_id')
            ->whereNull('tombstones.assertion_id')
            ->where('search.tenant_key', $tenantKey);

        foreach ($query->terms as $term) {
            $builder->where('search.content_normalized', 'like', '% '.$term.'%');
        }

        if ($query->subjectTypes !== []) {
            $builder->whereIn('search.subject_type', $query->subjectTypes);
        }

        if ($query->predicates !== []) {
            $builder->whereIn('search.predicate', $query->predicates);
        }

        if ($query->sourceReferences !== []) {
            $builder->whereIn('search.source_reference', $query->sourceReferences);
        }

        return $builder;
    }

    /**
     * Score the matched rows and order them.
     *
     * Ordering is score first, then most recently recorded, then assertion id, then
     * field path. The three tie-breaks after score are there so an equal-scoring set
     * comes back in one stable order: pagination that reshuffled between pages would
     * drop and repeat results.
     *
     * @param  list<stdClass>  $rows
     * @return list<SearchHit>
     */
    private function rank(SearchQuery $query, array $rows): array
    {
        $assertions = $this->assertions(array_map(fn (stdClass $row): string => (string) $row->assertion_id, $rows));

        $scored = [];

        foreach ($rows as $row) {
            $assertion = $assertions[(string) $row->assertion_id] ?? null;

            if ($assertion === null) {
                continue;
            }

            $content = (string) $row->content;

            $scored[] = [
                'hit' => new SearchHit(
                    $assertion,
                    (string) $row->field,
                    self::snippet($content, $query->terms[0]),
                    self::score($query, $content),
                ),
                'recorded_at' => (string) $row->recorded_at,
                'assertion_id' => (string) $row->assertion_id,
                'field' => (string) $row->field,
            ];
        }

        usort($scored, fn (array $left, array $right): int => $right['hit']->score <=> $left['hit']->score
            ?: $right['recorded_at'] <=> $left['recorded_at']
            ?: $left['assertion_id'] <=> $right['assertion_id']
            ?: $left['field'] <=> $right['field']);

        return array_map(fn (array $entry): SearchHit => $entry['hit'], $scored);
    }

    /**
     * How well one field answers the query.
     *
     * Density — matched terms over the field's own length — is what keeps a short
     * commit subject from being buried under a long document that happens to repeat
     * the word. Contiguity earns a fixed point on top, because a field containing the
     * caller's words in the order they typed them is usually the one they meant.
     */
    private static function score(SearchQuery $query, string $content): float
    {
        $normalized = SearchText::normalize($content);
        $padded = ' '.$normalized.' ';
        $tokens = $normalized === '' ? 1 : count(explode(' ', $normalized));

        $occurrences = 0;

        foreach ($query->terms as $term) {
            $occurrences += substr_count($padded, ' '.$term);
        }

        $adjacent = count($query->terms) > 1 && str_contains($padded, ' '.$query->phrase()) ? 1.0 : 0.0;

        return round($occurrences / $tokens + $adjacent, 6);
    }

    /** The matched text in context, taken from the stored content rather than its normalized form. */
    private static function snippet(string $content, string $term): string
    {
        $flattened = trim((string) preg_replace('/\s+/u', ' ', $content));
        $position = mb_stripos($flattened, $term);
        $start = $position === false ? 0 : max(0, $position - self::SNIPPET_LEAD);
        $snippet = mb_substr($flattened, $start, self::SNIPPET_LENGTH);

        return ($start > 0 ? '…' : '')
            .$snippet
            .(mb_strlen($flattened) > $start + self::SNIPPET_LENGTH ? '…' : '');
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, AbstractHistoricalAssertion>
     */
    private function assertions(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $assertions = [];

        foreach ($this->connection->table('funes_historical_assertions')->whereIn('id', array_unique($ids))->get() as $stored) {
            $assertions[(string) $stored->id] = $this->hydrate($stored);
        }

        return $assertions;
    }

    private function hydrate(stdClass $stored): AbstractHistoricalAssertion
    {
        try {
            $document = json_decode((string) $stored->document, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new SearchIndexUnavailable('A stored historical assertion document is not decodable JSON.');
        }

        if (! is_array($document)) {
            throw new SearchIndexUnavailable('A stored historical assertion document must be a JSON object.');
        }

        return HistoricalAssertionCodec::decode($document);
    }

    private static function tenantKey(TenantScope $tenant): string
    {
        return hash('sha256', json_encode($tenant->toArray(), JSON_THROW_ON_ERROR));
    }
}
