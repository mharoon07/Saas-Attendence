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
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('device_in_id')->nullable()->after('notes')->constrained('devices')->nullOnDelete();
            $table->foreignId('device_out_id')->nullable()->after('device_in_id')->constrained('devices')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['device_in_id']);
            $table->dropForeign(['device_out_id']);
            $table->dropColumn(['device_in_id', 'device_out_id']);
        });
    }
};
