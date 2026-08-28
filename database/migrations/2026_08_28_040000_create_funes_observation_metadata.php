<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funes_observation_metadata', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('observation_id')->constrained('funes_observations');
            $table->foreignUlid('provenance_id')->constrained('funes_observation_provenance');
            $table->string('namespace');
            $table->string('schema_version');
            $table->json('attributes');
            $table->char('fingerprint', 64)->unique();
            $table->timestampTz('recorded_at');
            $table->index(['observation_id', 'namespace', 'schema_version'], 'funes_metadata_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funes_observation_metadata');
    }
};
