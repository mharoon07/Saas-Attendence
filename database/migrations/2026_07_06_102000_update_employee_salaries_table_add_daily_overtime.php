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
            $table->dropColumn('salary');
            $table->decimal('monthly_salary', 10, 2)->default(0)->after('currency');
            $table->decimal('daily_salary', 10, 2)->default(0)->after('monthly_salary');
            $table->decimal('overtime_rate', 10, 2)->default(0)->after('daily_salary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_salaries', function (Blueprint $table) {
            $table->dropColumn(['monthly_salary', 'daily_salary', 'overtime_rate']);
            $table->integer('salary')->after('currency');
        });
    }
};
