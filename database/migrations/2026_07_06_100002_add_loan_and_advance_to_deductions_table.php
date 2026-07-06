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
            $table->decimal('loan_deduction', 10, 2)->default(0)->after('union_fees');
            $table->decimal('advance_payment_deduction', 10, 2)->default(0)->after('loan_deduction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deductions', function (Blueprint $table) {
            $table->dropColumn(['loan_deduction', 'advance_payment_deduction']);
        });
    }
};
