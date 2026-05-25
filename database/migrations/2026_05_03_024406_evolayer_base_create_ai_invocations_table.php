<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evolayer_base_ai_invocations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('feature_key', 64)->index();
            $table->string('route_name', 128)->nullable();

            $table->string('status', 32)->index();

            // Subject polymorph (nullable) — variant attribution: Commerce Customer/Order, SaaS Tenant/Subscription, etc.
            $table->nullableUlidMorphs('subject');

            // Tenant scope (nullable) — populated by RLS-using consumers; null for single-tenant apps.
            $table->string('tenant_id', 64)->nullable()->index();

            $table->json('request_projection');
            $table->json('response_projection')->nullable();

            $table->json('raw_request')->nullable();
            $table->json('raw_response')->nullable();

            $table->string('failure_type', 64)->nullable();
            $table->text('failure_message')->nullable();
            $table->string('exception_class', 191)->nullable();

            // Cost / metering (nullable) — Commerce/SaaS billing attribution; provider-reported usage.
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('cost_cents')->nullable();
            $table->string('cost_currency', 3)->nullable();

            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evolayer_base_ai_invocations');
    }
};
