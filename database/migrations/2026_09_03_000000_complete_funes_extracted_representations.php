<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funes_extractions', function (Blueprint $table): void {
            $table->string('representation_type')->nullable()->after('observation_id');
            $table->char('input_hash', 64)->nullable()->after('version');
            $table->string('failure_code')->nullable()->after('failure');
            $table->json('failure_details')->nullable()->after('failure_code');
        });

        DB::table('funes_extractions')->orderBy('id')->eachById(function (object $row): void {
            $inputHash = DB::table('funes_observations')->where('id', $row->observation_id)->value('payload_hash');
            DB::table('funes_extractions')->where('id', $row->id)->update([
                'representation_type' => $row->extractor,
                'input_hash' => $inputHash,
                'failure_code' => $row->status === 'failed' ? 'extraction_failed' : null,
                'failure_details' => $row->status === 'failed' ? '{}' : null,
            ]);
        });

        Schema::table('funes_extractions', function (Blueprint $table): void {
            $table->string('representation_type')->nullable(false)->change();
            $table->char('input_hash', 64)->nullable(false)->change();
            $table->dropUnique('funes_extractions_observation_id_extractor_version_unique');
            $table->unique(['observation_id', 'representation_type', 'extractor', 'version'], 'funes_extractions_identity_unique');
        });
    }

    public function down(): void
    {
        Schema::table('funes_extractions', function (Blueprint $table): void {
            $table->dropUnique('funes_extractions_identity_unique');
            $table->unique(['observation_id', 'extractor', 'version']);
            $table->dropColumn(['representation_type', 'input_hash', 'failure_code', 'failure_details']);
        });
    }
};
