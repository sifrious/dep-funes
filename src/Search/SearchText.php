<?php

declare(strict_types=1);

namespace Sifrious\Funes\Search;

use Sifrious\Funes\Assertion\AbstractHistoricalAssertion;

/**
 * What text a historical assertion offers to a search index, and how any text is
 * reduced before it is compared.
 *
 * Both halves belong together because indexing and querying must reduce text the
 * same way. If they diverged, a term a caller typed could stop matching the text it
 * came from, and the index would quietly answer a different question than the one
 * asked.
 *
 * Normalization lowercases, keeps the characters that carry identity in this domain
 * — hyphen, dot, colon, slash, hash, at, plus, so `MME-1887`, `git.sha`, and
 * `sifrious/funes` survive as single terms — and turns everything else into a
 * separator. That the discarded set includes `%` and `_` is not incidental: it means
 * a normalized term can never carry a SQL LIKE wildcard into a query.
 */
final readonly class SearchText
{
    /** How deep into a structured assertion value indexable strings are collected. */
    private const MAX_DEPTH = 4;

    /** The longest field path the index will store. */
    private const MAX_FIELD_LENGTH = 191;

    /**
     * The indexable strings in one assertion's value, keyed by field path.
     *
     * Only the claimed value is text. Subject and source identifiers are resolved
     * deterministically by the identity registry rather than scored as prose, and
     * indexing them here would let a guessed identifier surface through search.
     *
     * @return array<string, string>
     */
    public static function fields(AbstractHistoricalAssertion $assertion): array
    {
        $fields = [];

        self::collect($assertion->value(), 'value', $fields, 0);

        return $fields;
    }

    /** Reduce text to its comparable form: lowercase terms separated by single spaces. */
    public static function normalize(string $text): string
    {
        $mapped = preg_replace('/[^\p{L}\p{N}\-.:\/#@+]+/u', ' ', mb_strtolower($text)) ?? '';

        return trim(preg_replace('/ +/', ' ', $mapped) ?? '');
    }

    /**
     * The distinct terms in a piece of text, in the order they first appear.
     *
     * @return list<string>
     */
    public static function terms(string $text): array
    {
        $normalized = self::normalize($text);

        if ($normalized === '') {
            return [];
        }

        return array_values(array_unique(explode(' ', $normalized)));
    }

    /**
     * @param  array<string, string>  $fields
     */
    private static function collect(mixed $value, string $path, array &$fields, int $depth): void
    {
        if (is_string($value)) {
            if (trim($value) !== '' && strlen($path) <= self::MAX_FIELD_LENGTH) {
                $fields[$path] = $value;
            }

            return;
        }

        if (! is_array($value) || $depth >= self::MAX_DEPTH) {
            return;
        }

        foreach ($value as $key => $item) {
            self::collect($item, $path.'.'.$key, $fields, $depth + 1);
        }
    }
}
