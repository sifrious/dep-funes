<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funes_entity_associations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('observation_id')->constrained('funes_observations');
            $table->string('role');
            $table->json('entity_reference');
            $table->char('entity_reference_key', 64)->index();
            $table->char('fingerprint', 64)->unique();
            $table->timestampTz('recorded_at');
            $table->index(['observation_id', 'role'], 'funes_entity_association_lookup');
        });

        Schema::create('funes_entity_association_provenance', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('association_id')->constrained('funes_entity_associations');
            $table->foreignUlid('provenance_id')->constrained('funes_observation_provenance');
            $table->timestampTz('recorded_at');
            $table->unique(['association_id', 'provenance_id'], 'funes_entity_association_provenance_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funes_entity_association_provenance');
        Schema::dropIfExists('funes_entity_associations');
    }
};
