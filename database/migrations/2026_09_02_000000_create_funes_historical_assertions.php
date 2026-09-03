<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funes_historical_assertions', function (Blueprint $t): void {
            $t->string('id', 191)->primary();
            $t->char('fingerprint', 64)->unique();
            $t->string('assertion_type', 16);
            $t->char('subject_key', 64);
            $t->string('predicate', 128);
            $t->char('tenant_key', 64);
            $t->timestampTz('occurred_at', 6)->nullable();
            $t->timestampTz('observed_at', 6);
            $t->timestampTz('recorded_at', 6);
            $t->json('document');
            $t->index(['tenant_key', 'subject_key', 'predicate', 'recorded_at'], 'funes_assertion_timeline');
            $t->index(['tenant_key', 'subject_key', 'recorded_at'], 'funes_assertion_subject');
        });
        Schema::create('funes_assertion_tombstones', function (Blueprint $t): void {
            $t->string('assertion_id', 191)->primary();
            $t->foreign('assertion_id')->references('id')->on('funes_historical_assertions');
            $t->string('reason', 512);
            $t->json('authorization_context');
            $t->timestampTz('tombstoned_at', 6);
            $t->index('tombstoned_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funes_assertion_tombstones');
        Schema::dropIfExists('funes_historical_assertions');
    }
};
