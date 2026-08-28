<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funes_idempotency_keys', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('accepted_type')->nullable();
            $table->ulid('accepted_id')->nullable();
            $table->char('payload_hash', 64)->nullable();
            $table->timestampTz('reserved_at');
            $table->timestampTz('accepted_at')->nullable();
        });

        Schema::create('funes_outbox_messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('type')->index();
            $table->string('accepted_type');
            $table->ulid('accepted_id');
            $table->json('payload');
            $table->timestampTz('created_at');
            $table->timestampTz('published_at')->nullable()->index();
        });

        Schema::table('funes_observations', function (Blueprint $table): void {
            $table->timestampTz('occurred_at')->nullable()->after('observed_at');
        });
    }

    public function down(): void
    {
        Schema::table('funes_observations', function (Blueprint $table): void {
            $table->dropColumn('occurred_at');
        });

        Schema::dropIfExists('funes_outbox_messages');
        Schema::dropIfExists('funes_idempotency_keys');
    }
};
