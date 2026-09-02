<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funes_graph_appends', function (Blueprint $t): void {
            $t->id();
            $t->string('event_id', 191)->unique();
            $t->char('event_fingerprint', 64);
            $t->char('append_fingerprint', 64);
            $t->string('actor_reference', 512);
            $t->string('tenant_reference', 512);
            $t->timestampTz('appended_at');
        });
        Schema::create('funes_entity_relations', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->foreignUlid('subject_entity_id')->constrained('funes_entities');
            $t->string('predicate', 128);
            $t->foreignUlid('object_entity_id')->constrained('funes_entities');
            $t->string('assertion_type', 16);
            $t->string('source_reference', 512);
            $t->decimal('confidence', 8, 7)->nullable();
            $t->timestampTz('occurred_at')->nullable();
            $t->char('fingerprint', 64)->unique();
            $t->timestampTz('recorded_at');
            $t->index(['subject_entity_id', 'predicate']);
            $t->index(['object_entity_id', 'predicate']);
        });
        Schema::create('funes_entity_relation_evidence', function (Blueprint $t): void {
            $t->id();
            $t->foreignUlid('relation_id')->constrained('funes_entity_relations')->cascadeOnDelete();
            $t->string('evidence_reference', 512);
            $t->unique(['relation_id', 'evidence_reference'], 'funes_relation_evidence_unique');
        });
        Schema::create('funes_graph_append_facts', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('append_id')->constrained('funes_graph_appends');
            $t->string('fact_kind', 16);
            $t->string('fact_reference', 191);
            $t->unique(['append_id', 'fact_kind', 'fact_reference'], 'funes_graph_append_fact_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funes_graph_append_facts');
        Schema::dropIfExists('funes_entity_relation_evidence');
        Schema::dropIfExists('funes_entity_relations');
        Schema::dropIfExists('funes_graph_appends');
    }
};
