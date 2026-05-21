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
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'is_remote')) {
                $table->dropColumn('is_remote');
            }
        });

        Schema::table('archived_employees', function (Blueprint $table) {
            if (Schema::hasColumn('archived_employees', 'was_remote')) {
                $table->dropColumn('was_remote');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('is_remote')->default(false);
        });

        Schema::table('archived_employees', function (Blueprint $table) {
            $table->boolean('was_remote')->default(false);
        });
    }
};
