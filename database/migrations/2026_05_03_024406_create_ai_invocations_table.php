<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_invocations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('feature_key', 64)->index();
            $table->string('route_name', 128)->nullable();

            $table->string('status', 32)->index();

            $table->json('request_projection');
            $table->json('response_projection')->nullable();

            $table->json('raw_request')->nullable();
            $table->json('raw_response')->nullable();

            $table->string('failure_type', 64)->nullable();
            $table->text('failure_message')->nullable();
            $table->string('exception_class', 191)->nullable();

            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_invocations');
    }
};
