<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AutoSignOutHandle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:auto-sign-out';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically signs out employees whose shifts have ended but they forgot to sign out.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get active attendances where sign_in_time is set but sign_off_time is null
        $activeAttendances = \App\Models\Attendance::with('employee')
            ->whereNotNull('sign_in_time')
            ->whereNull('sign_off_time')
            // To limit search, assume they won't be signed in for more than 48 hours
            ->where('date', '>=', \Carbon\Carbon::now()->subDays(2)->toDateString())
            ->get();

        $now = \Carbon\Carbon::now();
        $count = 0;

        foreach ($activeAttendances as $attendance) {
            $shift = $attendance->employee->activeShift();
            if (!$shift) continue;

            $shiftEnd = \Carbon\Carbon::parse($shift->end_time);
            
            // Create a full carbon object for the shift end relative to the attendance date
            $expectedSignOut = \Carbon\Carbon::parse($attendance->date . ' ' . $shift->end_time);
            
            // If shift ends on the next day (e.g. overnight shift 7 PM to 7 AM)
            if ($shiftEnd->lessThan(\Carbon\Carbon::parse($shift->start_time))) {
                $expectedSignOut->addDay();
            }

            // If the current time is at or past the expected sign out time
            if ($now->greaterThanOrEqualTo($expectedSignOut)) {
                $attendance->update([
                    'sign_off_time' => $expectedSignOut->format('H:i:s'),
                ]);
                $count++;
                $this->info("Signed out employee {$attendance->employee_id} for date {$attendance->date} at {$expectedSignOut->format('H:i:s')}");
            }
        }
        
        $this->info("Auto sign-out complete. Signed out {$count} employees.");
    }
}
