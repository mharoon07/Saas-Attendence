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
        Schema::table('additions', function (Blueprint $table) {
            $table->dropColumn(['rewards', 'incentives', 'reimbursements', 'shift_differentials', 'commissions']);
            $table->json('custom_items')->nullable();
        });

        Schema::table('deductions', function (Blueprint $table) {
            $table->dropColumn(['social_security_contributions', 'health_insurance', 'retirement_plan', 'benefits', 'union_fees']);
            $table->json('custom_items')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('additions', function (Blueprint $table) {
            $table->decimal('rewards', 10, 2)->default(0);
            $table->decimal('incentives', 10, 2)->default(0);
            $table->decimal('reimbursements', 10, 2)->default(0);
            $table->decimal('shift_differentials', 10, 2)->default(0);
            $table->decimal('commissions', 10, 2)->default(0);
            $table->dropColumn('custom_items');
        });

        Schema::table('deductions', function (Blueprint $table) {
            $table->decimal('social_security_contributions', 10, 2)->default(0);
            $table->decimal('health_insurance', 10, 2)->default(0);
            $table->decimal('retirement_plan', 10, 2)->default(0);
            $table->decimal('benefits', 10, 2)->default(0);
            $table->decimal('union_fees', 10, 2)->default(0);
            $table->dropColumn('custom_items');
        });
    }
};
