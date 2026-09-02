<?php

declare(strict_types=1);

namespace Sifrious\Funes\Graph;

use InvalidArgumentException;
use Sifrious\EventContract\Contracts\EventEnvelopeContract;

final readonly class HistoricalAppend
{
    /** @var list<HistoricalEntityDraft> */
    public array $entities;

    /** @var list<HistoricalIdentifierDraft> */
    public array $identifiers;

    /** @var list<HistoricalRelationDraft> */
    public array $relations;

    public function __construct(
        public EventEnvelopeContract $event,
        public HistoricalAppendAuthorizationContract $authorization,
        mixed $entities = [],
        mixed $identifiers = [],
        mixed $relations = [],
    ) {
        if (! is_array($entities) || ! is_array($identifiers) || ! is_array($relations)) {
            throw new InvalidArgumentException('Historical append entities, identifiers, and relations must be arrays.');
        }
        foreach ($entities as $entity) {
            if (! $entity instanceof HistoricalEntityDraft) {
                throw new InvalidArgumentException('Historical append entities must be HistoricalEntityDraft values.');
            }
        }
        foreach ($identifiers as $identifier) {
            if (! $identifier instanceof HistoricalIdentifierDraft) {
                throw new InvalidArgumentException('Historical append identifiers must be HistoricalIdentifierDraft values.');
            }
        }
        foreach ($relations as $relation) {
            if (! $relation instanceof HistoricalRelationDraft) {
                throw new InvalidArgumentException('Historical append relations must be HistoricalRelationDraft values.');
            }
        }

        $this->entities = array_values($entities);
        $this->identifiers = array_values($identifiers);
        $this->relations = array_values($relations);
    }

    public function idempotencyKey(): string
    {
        return $this->event->idempotencyKey();
    }
}
