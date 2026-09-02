<?php

declare(strict_types=1);

namespace Sifrious\Funes\Search;

use InvalidArgumentException;

/**
 * One full-text request: what to look for, what to look in, and how much to return.
 *
 * A query is text plus optional filters. The filters exist so a caller can ask
 * "find a commit whose message says X" rather than only "find X anywhere", which is
 * the difference between a usable discovery path and a pile of results.
 *
 * Terms are the normalized tokens of the text, and every one of them must match for
 * a field to be a hit. Narrowing on more words is the behavior people expect from a
 * search box, and it keeps a long query from returning the whole corpus.
 */
final readonly class SearchQuery
{
    public const DEFAULT_LIMIT = 20;

    public const MAX_LIMIT = 100;

    /** @var list<string> */
    public array $terms;

    /** @var list<string> */
    public array $subjectTypes;

    /** @var list<string> */
    public array $predicates;

    /** @var list<string> */
    public array $sourceReferences;

    public function __construct(
        public string $text,
        mixed $subjectTypes = [],
        mixed $predicates = [],
        mixed $sourceReferences = [],
        public int $limit = self::DEFAULT_LIMIT,
        public int $offset = 0,
    ) {
        $this->terms = SearchText::terms($text);

        if ($this->terms === []) {
            throw new InvalidArgumentException('A full-text query requires at least one searchable term.');
        }

        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new InvalidArgumentException('A full-text query returns between one and '.self::MAX_LIMIT.' hits.');
        }

        if ($offset < 0) {
            throw new InvalidArgumentException('A full-text query offset cannot be negative.');
        }

        $this->subjectTypes = self::stringList($subjectTypes, 'subject types');
        $this->predicates = self::stringList($predicates, 'predicates');
        $this->sourceReferences = self::stringList($sourceReferences, 'source references');
    }

    /** The normalized query as one contiguous phrase, used to reward adjacency. */
    public function phrase(): string
    {
        return implode(' ', $this->terms);
    }

    /**
     * The query text when it looks like an identifier rather than prose.
     *
     * A caller offers this to identity resolution before scoring anything: someone
     * who pastes a SHA or a ticket key wants that exact object, and a relevance
     * ranking is a poor way to return something a deterministic lookup already knows.
     * Search remains the fallback when resolution finds nothing.
     */
    public function identifierCandidate(): ?string
    {
        $candidate = trim($this->text);

        if ($candidate === '' || preg_match('/\s/u', $candidate) === 1) {
            return null;
        }

        // A git object name, an external work-item key such as MME-1887, or anything
        // already namespaced the way stable references are.
        if (preg_match('/^[0-9a-f]{7,40}$/i', $candidate) === 1) {
            return $candidate;
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9]*-\d+$/', $candidate) === 1) {
            return $candidate;
        }

        return str_contains($candidate, ':') || str_contains($candidate, '/') ? $candidate : null;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $values, string $name): array
    {
        if (! is_array($values)) {
            throw new InvalidArgumentException("Full-text query {$name} must be a list of strings.");
        }

        $list = [];

        foreach ($values as $value) {
            if (! is_string($value) || trim($value) === '') {
                throw new InvalidArgumentException("Full-text query {$name} must be non-empty strings.");
            }

            $list[] = $value;
        }

        return array_values(array_unique($list));
    }
}
