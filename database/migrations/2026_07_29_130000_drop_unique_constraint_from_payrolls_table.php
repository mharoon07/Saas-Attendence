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
            // First add a non-unique index on employee_id so the foreign key constraint stays valid
            $table->index('employee_id', 'payrolls_employee_id_index');
            // Then drop the unique constraint on (employee_id, due_date)
            $table->dropUnique('payrolls_employee_id_due_date_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->unique(['employee_id', 'due_date'], 'payrolls_employee_id_due_date_unique');
            $table->dropIndex('payrolls_employee_id_index');
        });
    }
};
