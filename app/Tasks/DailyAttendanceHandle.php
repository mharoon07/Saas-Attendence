<?php

namespace App\Tasks;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Globals;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;

class DailyAttendanceHandle
{
    public function __invoke(): void
    {
        logger("Daily Attendance Maintenance is running");
        Artisan::call('down --retry=1 --secret=HelloKittyImSoPretty --render="errors::503_daily"');

        // This will run at 12:00 AM every day, so we need to check yesterday's attendance
        $carbon = CarbonImmutable::now()->subDay();
        $date = $carbon->toDateString();

        $commonServices = new \App\Services\CommonServices();

        // This condition is to check if yesterday was a day off (weekend or public holiday)
        if ($commonServices->isDayOff($date)) {
            logger("Yesterday was a day off (weekend/holiday), nothing to do in the attendance scheduler");
            Artisan::call('up');
            return;
        }

        // Find attendances that haven't signed out
        $unfinishedAttendances = Attendance::with('employee')->where('date', $date)->whereNull('sign_off_time')->get();
        $now = \Carbon\Carbon::now();
        foreach ($unfinishedAttendances as $attendance) {
            $shift = $attendance->employee->activeShift();
            if ($shift) {
                $shiftEnd = \Carbon\Carbon::parse($shift->end_time);
                $expectedSignOut = \Carbon\Carbon::parse($attendance->date . ' ' . $shift->end_time);
                if ($shiftEnd->lessThan(\Carbon\Carbon::parse($shift->start_time))) {
                    $expectedSignOut->addDay();
                }
                
                // If expected sign out is still in the future, don't mark as missed! (e.g. overnight shift)
                if ($now->lessThan($expectedSignOut)) {
                    continue;
                }
            }

            // Otherwise, mark as missed (e.g. shift ended but auto-sign-out somehow missed them)
            $attendance->update([
                'status' => 'missed',
                'notes' => 'Employee did not sign off - Marked as Missed'
            ]);
        }

        // If a user does not have attendance taken that day, create a record and mark it as missed
        $employees = Employee::all();
        foreach ($employees as $employee) {
            if (!$employee->attendances()->where('date', $date)->exists()) {
                $status = 'missed';
                $notes = 'Automatically Marked as Missed';

                // Check for weekly off day
                if (strtolower($carbon->englishDayOfWeek) === strtolower($employee->weekly_off_day)) {
                    continue; // Skip creating a missed/absent/leave record entirely
                }

                $employee->attendances()->create([
                    'date' => $date,
                    'status' => $status,
                    'sign_in_time' => NULL,
                    'sign_off_time' => NULL,
                    'notes' => $notes,
                ]);
            }
        }

        Artisan::call('up');
        logger("DailyAttendanceHandle Completed");
    }
}
