<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add PKR (Pakistani Rupee) to the currency enum on employee_salaries.
     * The frontend already exposes PKR as an option but the DB enum was missing it.
     */
    public function up(): void
    {
        // Alter the enum column to include PKR alongside existing values
        DB::statement("ALTER TABLE `employee_salaries` MODIFY `currency` ENUM('EGP', 'USD', 'EUR', 'GBP', 'CAD', 'KWD', 'SAR', 'AED', 'PKR') NOT NULL DEFAULT 'EGP'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `employee_salaries` MODIFY `currency` ENUM('EGP', 'USD', 'EUR', 'GBP', 'CAD', 'KWD', 'SAR', 'AED') NOT NULL DEFAULT 'EGP'");
    }
};
