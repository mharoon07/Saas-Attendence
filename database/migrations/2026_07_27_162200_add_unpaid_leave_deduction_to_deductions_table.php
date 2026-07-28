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
        Schema::table('deductions', function (Blueprint $table) {
            $table->decimal('unpaid_leave_deduction', 10, 2)->default(0)->after('undertime');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deductions', function (Blueprint $table) {
            $table->dropColumn('unpaid_leave_deduction');
        });
    }
};
