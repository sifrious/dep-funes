<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funes_sources', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('reference')->unique();
            $table->string('name');
            $table->timestampTz('created_at');
        });

        Schema::create('funes_resources', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('source_id')->constrained('funes_sources');
            $table->text('canonical_reference');
            $table->char('reference_hash', 64);
            $table->timestampTz('created_at');
            $table->unique(['source_id', 'reference_hash']);
        });

        Schema::create('funes_payloads', function (Blueprint $table): void {
            $table->char('hash', 64)->primary();
            $table->binary('contents');
            $table->unsignedBigInteger('byte_size');
            $table->timestampTz('created_at');
        });

        Schema::create('funes_observations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('source_id')->constrained('funes_sources');
            $table->foreignUlid('resource_id')->constrained('funes_resources');
            $table->char('payload_hash', 64);
            $table->string('content_type');
            $table->char('fingerprint', 64);
            $table->json('metadata');
            $table->timestampTz('observed_at');
            $table->timestampTz('ingested_at');
            $table->unique(['resource_id', 'payload_hash']);
            $table->foreign('payload_hash')->references('hash')->on('funes_payloads');
        });

        Schema::create('funes_discoveries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('observation_id')->constrained('funes_observations');
            $table->foreignUlid('parent_resource_id')->constrained('funes_resources');
            $table->foreignUlid('resource_id')->constrained('funes_resources');
            $table->string('relationship');
            $table->timestampTz('created_at');
            $table->unique(['observation_id', 'parent_resource_id', 'resource_id', 'relationship']);
        });

        Schema::create('funes_extractions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('observation_id')->constrained('funes_observations');
            $table->string('extractor');
            $table->string('version');
            $table->string('status');
            $table->json('result')->nullable();
            $table->text('failure')->nullable();
            $table->char('fingerprint', 64);
            $table->timestampTz('recorded_at');
            $table->unique(['observation_id', 'extractor', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funes_extractions');
        Schema::dropIfExists('funes_discoveries');
        Schema::dropIfExists('funes_observations');
        Schema::dropIfExists('funes_payloads');
        Schema::dropIfExists('funes_resources');
        Schema::dropIfExists('funes_sources');
    }
};
