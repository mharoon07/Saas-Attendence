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
        Schema::table('globals', function (Blueprint $table) {
            $table->unsignedInteger('late_threshold_minutes')->default(15)->after('absence_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('globals', function (Blueprint $table) {
            $table->dropColumn('late_threshold_minutes');
        });
    }
};
