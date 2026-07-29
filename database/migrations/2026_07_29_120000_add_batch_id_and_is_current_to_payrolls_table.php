<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            if (!Schema::hasColumn('payrolls', 'batch_id')) {
                $table->string('batch_id')->nullable()->after('employee_id');
            }
            if (!Schema::hasColumn('payrolls', 'is_current')) {
                $table->boolean('is_current')->default(true)->after('batch_id');
            }
        });

        // Initialize existing records so only the latest created batch remains is_current = true
        $latestBatchTime = DB::table('payrolls')->max('created_at');
        if ($latestBatchTime) {
            DB::table('payrolls')
                ->where('created_at', '<', $latestBatchTime)
                ->update(['is_current' => false]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            if (Schema::hasColumn('payrolls', 'batch_id')) {
                $table->dropColumn('batch_id');
            }
            if (Schema::hasColumn('payrolls', 'is_current')) {
                $table->dropColumn('is_current');
            }
        });
    }
};
