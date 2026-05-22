<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            Schema::ensureVectorExtensionExists();

            Schema::table('form_submissions', function (Blueprint $table) {
                $table->vector('embedding', dimensions: 1536)->nullable()->index();
            });

            return;
        }

        Schema::table('form_submissions', function (Blueprint $table) {
            $table->json('embedding')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropColumn('embedding');
        });
    }
};
