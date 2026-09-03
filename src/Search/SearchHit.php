<?php

declare(strict_types=1);

namespace Sifrious\Funes\Search;

use Sifrious\Funes\Assertion\AbstractHistoricalAssertion;
use Sifrious\ReferenceContract\CrossPackageReference;

/**
 * One matched assertion, carried whole, plus why it matched.
 *
 * The hit hands back the canonical assertion rather than an index row, so a caller
 * receives the stable subject reference, source locator, provenance, evidence, and
 * temporal coordinates that make a result citable. The index knows nothing the
 * history does not; it only decided which assertions to fetch.
 *
 * The field and snippet answer the other half of a search result's obligation: not
 * merely that this record matched, but where.
 */
final readonly class SearchHit
{
    public function __construct(
        public AbstractHistoricalAssertion $assertion,
        public string $field,
        public string $snippet,
        public float $score,
    ) {}

    public function subject(): CrossPackageReference
    {
        return $this->assertion->subject();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'assertion_id' => $this->assertion->stableIdentity(),
            'assertion_type' => $this->assertion->assertionType()->value,
            'subject' => $this->subject()->toArray(),
            'predicate' => $this->assertion->predicate(),
            'field' => $this->field,
            'snippet' => $this->snippet,
            'score' => $this->score,
            'source' => [
                'source_reference' => $this->assertion->source()->sourceReference,
                'source_name' => $this->assertion->source()->sourceName,
                'resource_reference' => $this->assertion->source()->resourceReference,
            ],
            'provenance' => $this->assertion->provenance()?->toArray(),
            'recorded_at' => $this->assertion->recordedAt()->format('Y-m-d\TH:i:s.uP'),
        ];
    }
}
