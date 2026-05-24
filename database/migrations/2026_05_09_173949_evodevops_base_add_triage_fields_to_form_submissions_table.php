<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table): void {
            $table->string('triage_urgency', 16)->nullable()->after('status');
            $table->string('triage_sentiment', 16)->nullable()->after('triage_urgency');
            $table->text('triage_summary')->nullable()->after('triage_sentiment');
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table): void {
            $table->dropColumn(['triage_urgency', 'triage_sentiment', 'triage_summary']);
        });
    }
};
