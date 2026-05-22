<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_capabilities', function (Blueprint $table): void {
            // Soft-delete column set by ai:probe --reprobe-stale once a row
            // with an obsolete schema_hash has been successfully replaced by a
            // live-hash row. NULL means the row is still considered current.
            $table->timestampTz('superseded_at')->nullable()->after('probed_at');
            $table->index('superseded_at');
        });
    }

    public function down(): void
    {
        Schema::table('ai_capabilities', function (Blueprint $table): void {
            $table->dropIndex(['superseded_at']);
            $table->dropColumn('superseded_at');
        });
    }
};
