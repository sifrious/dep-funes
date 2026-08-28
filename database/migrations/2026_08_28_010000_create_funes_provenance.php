<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funes_observation_provenance', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('observation_id')->constrained('funes_observations');
            $table->foreignUlid('source_id')->constrained('funes_sources');
            $table->foreignUlid('resource_id')->constrained('funes_resources');
            $table->string('producer_reference');
            $table->string('producer_name');
            $table->timestampTz('occurred_at')->nullable();
            $table->timestampTz('observed_at');
            $table->timestampTz('recorded_at');
            $table->json('transformation_lineage');
            $table->char('fingerprint', 64)->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funes_observation_provenance');
    }
};
