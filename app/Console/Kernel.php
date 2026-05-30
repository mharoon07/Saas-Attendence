<?php

namespace App\Console;

use App\Models\Globals;
use App\Tasks\DailyAttendanceHandle;
use App\Tasks\MonthlyPayrollsHandle;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // This will use the timezone set at config/app.php
        $schedule->call(new DailyAttendanceHandle())->daily();    // Every Day at 00:00
        $schedule->command('activitylog:clean')->twiceMonthly(1, 16, '00:00'); // Clear Activity Log

        // Schedule monthly payrolls only if Globals record exists.
        $globals = Globals::first();
        if ($globals && is_numeric($globals->payroll_day) && $globals->payroll_day >= 1 && $globals->payroll_day <= 28) {
            $schedule->call(new MonthlyPayrollsHandle())->monthlyOn($globals->payroll_day, '00:00');
        } else {
            // If payroll_day is missing or invalid, do not schedule automatic payroll generation.
            // This prevents exceptions when Globals table is empty on fresh installs.
            \Log::warning('Monthly payrolls not scheduled: Globals.payroll_day not configured.');
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
