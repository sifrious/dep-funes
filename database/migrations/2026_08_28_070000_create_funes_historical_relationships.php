<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funes_historical_relationships', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('observation_id')->constrained('funes_observations');
            $table->string('type');
            $table->json('target_reference');
            $table->char('target_reference_key', 64)->index();
            $table->char('fingerprint', 64)->unique();
            $table->timestampTz('recorded_at');
            $table->index(['observation_id', 'type'], 'funes_historical_relationship_lookup');
        });

        Schema::create('funes_historical_relationship_provenance', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('relationship_id')->constrained('funes_historical_relationships');
            $table->foreignUlid('provenance_id')->constrained('funes_observation_provenance');
            $table->timestampTz('recorded_at');
            $table->unique(['relationship_id', 'provenance_id'], 'funes_historical_relationship_provenance_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funes_historical_relationship_provenance');
        Schema::dropIfExists('funes_historical_relationships');
    }
};
