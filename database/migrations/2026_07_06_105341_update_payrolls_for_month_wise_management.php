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
        Schema::table('payrolls', function (Blueprint $table) {
            $table->integer('payroll_month')->nullable()->after('due_date');
            $table->integer('payroll_year')->nullable()->after('payroll_month');
            $table->decimal('monthly_salary', 10, 2)->default(0)->after('base');
            $table->decimal('daily_salary', 10, 2)->default(0)->after('monthly_salary');
            $table->integer('regular_working_days')->default(0)->after('daily_salary');
            $table->integer('absent_days')->default(0)->after('regular_working_days');
            $table->integer('leave_days')->default(0)->after('absent_days');
            $table->decimal('overtime_hours', 10, 2)->default(0)->after('leave_days');
            $table->decimal('overtime_amount', 10, 2)->default(0)->after('overtime_hours');
            $table->decimal('gross_salary', 10, 2)->default(0)->after('total_payable');
            $table->decimal('net_salary', 10, 2)->default(0)->after('gross_salary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn([
                'payroll_month',
                'payroll_year',
                'monthly_salary',
                'daily_salary',
                'regular_working_days',
                'absent_days',
                'leave_days',
                'overtime_hours',
                'overtime_amount',
                'gross_salary',
                'net_salary',
            ]);
        });
    }
};
