<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('device_employee_id')->nullable()->unique()->after('id')
                ->comment('ZKTeco device PIN / User ID enrolled on the attendance machine');
        });
    }

    
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('device_employee_id');
        });
    }
};
