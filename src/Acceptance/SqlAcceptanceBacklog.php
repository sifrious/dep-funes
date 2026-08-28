<?php

declare(strict_types=1);

namespace Sifrious\Funes\Acceptance;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Sifrious\Funes\Persistence\ObservationStore;
use Sifrious\Funes\Value\Observation;

final readonly class SqlAcceptanceBacklog implements AcceptanceBacklog
{
    public function __construct(
        private ConnectionInterface $connection,
        private ObservationStore $observations,
    ) {}

    public function unkeyed(int $limit = 100): array
    {
        $ids = $this->backlog()
            ->orderBy('observations.id')
            ->limit($limit)
            ->pluck('observations.id')
            ->all();

        $observations = [];

        foreach ($ids as $id) {
            $observation = $this->observations->get((string) $id);

            if ($observation instanceof Observation) {
                $observations[] = $observation;
            }
        }

        return $observations;
    }

    public function unkeyedCount(): int
    {
        return $this->backlog()->count();
    }

    private function backlog(): Builder
    {
        return $this->connection->table('funes_observations as observations')
            ->leftJoin('funes_idempotency_keys as keys', function (JoinClause $join): void {
                $join->on('keys.accepted_id', '=', 'observations.id')
                    ->where('keys.accepted_type', '=', 'observation');
            })
            ->whereNull('keys.key');
    }
}
