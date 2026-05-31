<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evolayer_base_ai_capabilities', function (Blueprint $table): void {
            // Conditions-lite observation set (ADR-019). Forward infrastructure:
            // an array of {type, status: True|False|Unknown, reason, message,
            // schema_hash?, observed_at} tuples describing what a probe observed,
            // distinct from the product policy that consumes those observations.
            //
            // NULL today — no probe writes conditions yet (deferred to a separate
            // probe-evolution commit). probe_passed stays the authoritative
            // boolean and is the backwards-compatible projection of the
            // StructuredStreaming condition once conditions are populated.
            $table->json('conditions')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('evolayer_base_ai_capabilities', function (Blueprint $table): void {
            $table->dropColumn('conditions');
        });
    }
};
