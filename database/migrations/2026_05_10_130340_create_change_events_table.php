<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableUlidMorphs('subject');
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
        Schema::dropIfExists('change_events');
    }
};
