<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historical timestamps are written at microsecond precision, so the columns that hold
 * them must keep it. A second-precision column silently rounds on insert, which is the
 * kind of quiet history loss this package exists to prevent. SQLite stores these as
 * text and is unaffected either way; MySQL and PostgreSQL are not.
 */
return new class extends Migration
{
    /** @var array<string, array<string, bool>> Table to column, with whether the column is nullable. */
    private const COLUMNS = [
        'funes_sources' => ['created_at' => false],
        'funes_resources' => ['created_at' => false],
        'funes_payloads' => ['created_at' => false],
        'funes_observations' => ['observed_at' => false, 'ingested_at' => false, 'occurred_at' => true],
        'funes_discoveries' => ['created_at' => false],
        'funes_extractions' => ['recorded_at' => false],
        'funes_idempotency_keys' => ['reserved_at' => false, 'accepted_at' => true],
        'funes_outbox_messages' => ['created_at' => false, 'published_at' => true],
        'funes_observation_provenance' => ['occurred_at' => true, 'observed_at' => false, 'recorded_at' => false],
        'funes_entities' => ['created_at' => false],
        'funes_external_identities' => ['created_at' => false],
        'funes_identity_provenance' => ['recorded_at' => false],
        'funes_extraction_provenance' => ['recorded_at' => false],
        'funes_observation_metadata' => ['recorded_at' => false],
        'funes_observation_text' => ['recorded_at' => false],
        'funes_text_projection' => ['recorded_at' => false],
        'funes_entity_associations' => ['recorded_at' => false],
        'funes_entity_association_provenance' => ['recorded_at' => false],
        'funes_historical_relationships' => ['recorded_at' => false],
        'funes_historical_relationship_provenance' => ['recorded_at' => false],
        'funes_relationship_declarations' => ['recorded_at' => false],
        'funes_graph_appends' => ['appended_at' => false],
        'funes_entity_relations' => ['occurred_at' => true, 'recorded_at' => false],
    ];

    public function up(): void
    {
        $this->setPrecision(6);
    }

    public function down(): void
    {
        $this->setPrecision(0);
    }

    private function setPrecision(int $precision): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column => $nullable) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $t) use ($column, $precision, $nullable): void {
                    $definition = $t->timestampTz($column, $precision);

                    if ($nullable) {
                        $definition->nullable();
                    }

                    $definition->change();
                });
            }
        }
    }
};
