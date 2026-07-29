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
        Schema::table('loans', function (Blueprint $table) {
            if (!Schema::hasColumn('loans', 'deduction_type')) {
                $table->string('deduction_type')->default('percentage')->after('total_amount');
            }
            if (!Schema::hasColumn('loans', 'deduction_amount')) {
                $table->decimal('deduction_amount', 10, 2)->nullable()->after('deduction_percentage');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (Schema::hasColumn('loans', 'deduction_type')) {
                $table->dropColumn('deduction_type');
            }
            if (Schema::hasColumn('loans', 'deduction_amount')) {
                $table->dropColumn('deduction_amount');
            }
        });
    }
};
