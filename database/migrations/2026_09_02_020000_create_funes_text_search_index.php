<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funes_text_search_index', function (Blueprint $t): void {
            $t->id();
            $t->string('assertion_id', 191);
            $t->foreign('assertion_id')->references('id')->on('funes_historical_assertions');
            $t->char('tenant_key', 64);
            $t->string('subject_type', 128);
            $t->string('predicate', 128);
            $t->string('source_reference', 512);
            $t->string('field', 191);
            $t->longText('content');
            $t->longText('content_normalized');
            $t->timestampTz('recorded_at', 6);
            $t->unique(['assertion_id', 'field'], 'funes_text_search_field_unique');
            $t->index(['tenant_key', 'subject_type'], 'funes_text_search_scope');
            $t->index(['tenant_key', 'recorded_at'], 'funes_text_search_recency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funes_text_search_index');
    }
};
