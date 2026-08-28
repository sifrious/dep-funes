<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funes_entities', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('kind');
            $table->timestampTz('created_at');
        });

        Schema::create('funes_external_identities', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('entity_id')->constrained('funes_entities');
            $table->string('kind');
            $table->string('source_reference');
            $table->text('external_identifier');
            $table->char('external_identifier_hash', 64);
            $table->timestampTz('created_at');
            $table->unique(['kind', 'source_reference', 'external_identifier_hash'], 'funes_external_identity_unique');
            $table->foreign('source_reference')->references('reference')->on('funes_sources');
        });

        Schema::create('funes_identity_provenance', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('external_identity_id')->constrained('funes_external_identities');
            $table->foreignUlid('provenance_id')->constrained('funes_observation_provenance');
            $table->timestampTz('recorded_at');
            $table->unique(['external_identity_id', 'provenance_id'], 'funes_identity_provenance_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funes_identity_provenance');
        Schema::dropIfExists('funes_external_identities');
        Schema::dropIfExists('funes_entities');
    }
};
