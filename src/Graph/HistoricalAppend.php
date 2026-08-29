<?php
declare(strict_types=1);
namespace Sifrious\Funes\Graph;
final readonly class HistoricalAppend
{
    /** @param list<HistoricalEntityDraft> $entities @param list<HistoricalIdentifierDraft> $identifiers @param list<HistoricalRelationDraft> $relations */
    public function __construct(public string $idempotencyKey, public array $entities = [], public array $identifiers = [], public array $relations = []) {}
}
