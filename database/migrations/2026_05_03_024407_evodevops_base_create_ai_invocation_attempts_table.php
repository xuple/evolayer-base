<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_invocation_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('ai_invocation_id')->constrained('ai_invocations')->cascadeOnDelete();

            $table->unsignedSmallInteger('attempt')->default(1);

            $table->string('provider', 64);
            $table->string('provider_driver', 64)->nullable();
            $table->string('model', 191)->nullable();
            $table->string('capability_status', 32)->nullable();
            $table->string('output_mode', 32)->nullable();

            $table->string('status', 32)->index();

            $table->json('response_keys')->nullable();
            $table->json('missing_fields')->nullable();
            $table->json('invalid_fields')->nullable();

            $table->string('exception_class', 191)->nullable();
            $table->text('exception_message')->nullable();

            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_invocation_attempts');
    }
};
