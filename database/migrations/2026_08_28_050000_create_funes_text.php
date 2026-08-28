<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funes_observation_text', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('observation_id')->constrained('funes_observations');
            $table->foreignUlid('provenance_id')->constrained('funes_observation_provenance');
            $table->string('kind');
            $table->string('content_type');
            $table->string('language')->nullable();
            $table->longText('text');
            $table->char('text_hash', 64);
            $table->char('fingerprint', 64)->unique();
            $table->timestampTz('recorded_at');
            $table->index(['observation_id', 'kind'], 'funes_observation_text_lookup');
        });

        Schema::create('funes_text_projection', function (Blueprint $table): void {
            $table->string('document_id')->primary();
            $table->foreignUlid('observation_id')->constrained('funes_observations');
            $table->foreignUlid('provenance_id')->nullable()->constrained('funes_observation_provenance');
            $table->string('kind')->index();
            $table->string('content_type');
            $table->string('language')->nullable();
            $table->longText('text');
            $table->char('text_hash', 64);
            $table->timestampTz('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funes_text_projection');
        Schema::dropIfExists('funes_observation_text');
    }
};
