<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('evolayer_base_ai_capabilities', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // Identity columns — form the unique key for upsert resolution.
            $table->string('agent_class', 191);
            $table->string('provider', 64);
            $table->string('model', 191);
            $table->char('schema_hash', 64); // SHA-256 of the canonical JSON Schema output.

            // Human-readable grouping label (e.g. "thread_studio"). Descriptive only;
            // never used in upsert resolution — that is handled by the unique key above.
            $table->string('probe_schema', 64);

            // Capability verdict from the most recent probe.
            $table->string('status', 32);       // supported | experimental | blocked | unknown
            $table->string('output_mode', 32);  // json_schema | json_object | prompt_json | unsupported | unknown
            $table->boolean('probe_passed');
            $table->text('failure_reason')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();

            // Optional human-authored annotation (survives re-probes via updateOrCreate).
            $table->text('note')->nullable();

            $table->timestampTz('probed_at');
            $table->timestampsTz();

            // Unique key — drives upsert resolution in evolayer:ai:probe --persist.
            $table->unique(['agent_class', 'provider', 'model', 'schema_hash']);

            // Secondary index — per-agent queries used by modelOptions() in Step 2.
            $table->index('agent_class');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evolayer_base_ai_capabilities');
    }
};
