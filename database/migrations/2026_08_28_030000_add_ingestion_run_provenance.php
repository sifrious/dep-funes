<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funes_observation_provenance', function (Blueprint $table): void {
            $table->string('ingestion_run_reference')->nullable()->after('producer_name');
        });

        DB::table('funes_observation_provenance')
            ->orderBy('id')
            ->eachById(function (object $provenance): void {
                DB::table('funes_observation_provenance')
                    ->where('id', $provenance->id)
                    ->update(['ingestion_run_reference' => 'funes:legacy-observation/'.$provenance->id]);
            }, column: 'id');

        Schema::table('funes_observation_provenance', function (Blueprint $table): void {
            $table->string('ingestion_run_reference')->nullable(false)->change();
        });

        Schema::create('funes_extraction_provenance', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('extraction_id')->constrained('funes_extractions');
            $table->string('producer_reference');
            $table->string('producer_name');
            $table->string('ingestion_run_reference');
            $table->timestampTz('recorded_at');
            $table->unique(
                ['extraction_id', 'producer_reference', 'ingestion_run_reference'],
                'funes_extraction_provenance_unique',
            );
        });

        DB::table('funes_extractions')
            ->orderBy('id')
            ->eachById(function (object $extraction): void {
                DB::table('funes_extraction_provenance')->insert([
                    'id' => (string) Str::ulid(),
                    'extraction_id' => $extraction->id,
                    'producer_reference' => 'funes:legacy-extractor/'.$extraction->extractor,
                    'producer_name' => $extraction->extractor,
                    'ingestion_run_reference' => 'funes:legacy-extraction/'.$extraction->id,
                    'recorded_at' => $extraction->recorded_at,
                ]);
            }, column: 'id');
    }

    public function down(): void
    {
        Schema::dropIfExists('funes_extraction_provenance');

        Schema::table('funes_observation_provenance', function (Blueprint $table): void {
            $table->dropColumn('ingestion_run_reference');
        });
    }
};
