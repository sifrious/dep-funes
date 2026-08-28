<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funes_relationship_declarations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('relationship_id')->constrained('funes_historical_relationships');
            $table->foreignUlid('provenance_id')->constrained('funes_observation_provenance');
            $table->string('source_locator');
            $table->text('declared_value');
            $table->char('fingerprint', 64)->unique();
            $table->timestampTz('recorded_at');
            $table->index(['relationship_id', 'recorded_at'], 'funes_relationship_declaration_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funes_relationship_declarations');
    }
};
