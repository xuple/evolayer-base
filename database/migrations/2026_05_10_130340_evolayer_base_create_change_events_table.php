<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evolayer_base_change_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // Actor polymorph (nullable) — User by default; variants may record Customer / Tenant / system.
            // Replaces the old `actor_user_id` column; package is pre-release, no data migration needed.
            $table->string('actor_type')->nullable()->index();
            $table->string('actor_id')->nullable()->index();

            $table->nullableUlidMorphs('subject');

            // Tenant scope (nullable) — populated by RLS-using consumers.
            $table->string('tenant_id', 64)->nullable()->index();

            $table->string('event_name');
            $table->unsignedSmallInteger('event_version')->default(1);
            $table->ulid('correlation_id')->nullable()->index();
            $table->ulid('causation_id')->nullable()->index();
            $table->string('source')->default('app');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('properties')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('occurred_at')->index();
            $table->timestampsTz();

            $table->index(['event_name', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evolayer_base_change_events');
    }
};
