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
        Schema::table('employee_salaries', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_salaries', 'salary_type')) {
                $table->string('salary_type')->default('monthly')->after('currency');
            }
            if (!Schema::hasColumn('employee_salaries', 'hourly_rate')) {
                $table->decimal('hourly_rate', 10, 2)->nullable()->after('salary_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_salaries', function (Blueprint $table) {
            if (Schema::hasColumn('employee_salaries', 'salary_type')) {
                $table->dropColumn('salary_type');
            }
            if (Schema::hasColumn('employee_salaries', 'hourly_rate')) {
                $table->dropColumn('hourly_rate');
            }
        });
    }
};
