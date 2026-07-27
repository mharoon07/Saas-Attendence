<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Employee extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, LogsActivity;

    public $guard_name = 'web';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected $appends = [
        'employee_code',
    ];

    public function getEmployeeCodeAttribute(): string
    {
        $raw = !empty($this->device_employee_id) ? $this->device_employee_id : $this->id;
        $num = preg_replace('/[^0-9]/', '', (string)$raw);
        return 'EM-' . ($num ?: $this->id);
    }

    protected static function boot(): void
    {
        parent::boot();
        static::created(function ($model) {
            $model->normalized_name = NormalizeArabic($model->name);
            $model->save();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    /**************------- SALARIES -------*************/

    public function salaries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmployeeSalary::class);
    }

    // Active Salary
    public function salary()
    {
        $salary = $this->salaries()->where('end_date', null)->first();
        if (!$salary) {
            return ['USD', 0, $this->hired_on ?? now()->toDateString()];
        }
        return [$salary->currency, $salary->monthly_salary, $salary->start_date];
    }

    /**************------- Payrolls -------*************/

    public function payrolls(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    /**************------- Evaluations -------*************/

    // Active Salary
    public function evaluations()
    {
        return $this->hasMany(EmployeeEvaluation::class);
    }


    /**************------- POSITIONS -------*************/

    public function employeePositions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmployeePosition::class, 'employee_id');
//        return $this->hasManyThrough(Position::class, EmployeePosition::class, 'employee_id', 'id', 'id', 'position_id');
    }

    // WARNING: THIS FUNCTION ONLY FETCHES THE LAST ACTIVE POSITION. IF AN EMPLOYEE HAS MULTIPLE ACTIVE POSITIONS, THIS FUNCTION WILL ONLY RETURN THE LAST ONE
    // USE activePositions() if you want to retrieve all active positions.
    public function activePosition()
    {
        $position = $this->employeePositions()->where('end_date', null)->first();
        return $position ? $position->get() : collect();
    }

    public function activePositions(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->positions()->where('end_date', null)->get();
    }

    /**************------- Department -------*************/
    public function department(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Department::class, 'id', 'department_id');
    }

    /**************------- Department -------*************/


    public function manages(){
        return $this->hasMany(Manager::class, 'employee_id');
    }

    public function attendances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**************------- Shifts -------*************/

    public function shifts()
    {
        return $this->hasManyThrough(Shift::class, EmployeeShift::class, 'employee_id', 'id', 'id', 'shift_id');
    }

    public function employeeShifts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmployeeShift::class, 'employee_id');
    }
    public function activeShift()
    {
        $activeShift = $this->employeeShifts()
            ->with('shift')
            ->whereNull('end_date')
            ->first();
        return $activeShift ? $activeShift->shift : null;
    }
    public function activeShiftPeriod()
    {
        return $this->activeShift()?->shiftPeriod() ?? 8.0;
    }

    /**************------- Department -------*************/


    public function getAttended(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        // exclude absented ones
        return $this->attendances()->whereNotIn('status', ['missed', 'absent']);
    }
    public function getAttendedInYear($year): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        // exclude absented ones
        return $this->attendances()->whereNotIn('status', ['missed', 'absent'])->whereYear('date', $year);
    }
    public function getAbsentedInYear($year): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        // get absented ones
        return $this->attendances()->whereIn('status', ['missed', 'absent'])->whereYear('date', $year);
    }
    public function getAbsented(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        // get absented ones
        return $this->attendances()->whereIn('status', ['missed', 'absent']);
    }

    public function getYearStats($globalSettings = null){
        $globalSettings = $globalSettings ?? Globals::first();
        $weekendOffDays = [$this->weekly_off_day];
        $commonServices = new \App\Services\CommonServices();
        $thisYearData = $commonServices->calcOffDays($weekendOffDays, $this->hired_on);
        $holidaysThisYear = $commonServices->countHolidays($this->hired_on);
        return [
            "workingDaysThisYear" => $thisYearData['total_year_days'],
            "WeekendOffDaysThisYear" => $thisYearData['offDays'],
            "weekendOffDays" => $weekendOffDays, // Friday, Saturday.. etc
            "HolidaysThisYear" => $holidaysThisYear,
            "absence_limit" => $globalSettings ? $globalSettings->absence_limit : 30,
        ];
    }
    public function myStats(): array
    {
        // Get Data for current month
        $now = CarbonImmutable::now();
        $curDay = $now->day;
        $curMonth = $now->month;
        $curYear = $now->year;
        $monthEnd = $now->endOfMonth()->format('j');
        $globalSettings = Globals::first();
        $weekendOffDays = [$this->weekly_off_day];
        $commonServices = new \App\Services\CommonServices();
        $monthDates = [$curYear, $curMonth, 1, $curYear, $curMonth, $monthEnd];

        // Calculations for the entire month
        $holidaysCount = $commonServices->countHolidays($this->hired_on, $monthDates);
        $weekendsCount = $commonServices->calcOffDays($weekendOffDays, $this->hired_on, $monthDates);
        $workingDays = $monthEnd - $holidaysCount - $weekendsCount;

        // Calculations from the start of the month until today.
        $holidaysCountSoFar = $commonServices->countHolidays($this->hired_on, [$curYear, $curMonth, 1, $curYear, $curMonth, $curDay]);
        $workingDaysSoFar = $curDay - 1 -$holidaysCountSoFar - // -1 to exclude today
            $commonServices->calcOffDays($weekendOffDays, $this->hired_on, [$curYear, $curMonth, 1, $curYear, $curMonth, $curDay]);

        // Calculations for the entire year until today
        $workDaysSoFarThisYear = $now->startOfYear()->diffInDays($now) - $commonServices->countHolidays($this->hired_on, [$curYear, 1, 1, $curYear, $curMonth, $curDay]) -
            $commonServices->calcOffDays($weekendOffDays, $this->hired_on, [$curYear, 1, 1, $curYear, $curMonth, $curDay]);

        // Calculating attendance stats for the month
        // $totalAttendanceSoFar attendance from the start of the year (or hire date if they weren't hired this year) until today.

        $attended = $this->getAttended();
        $absented = $this->getAbsented();

        $monthAttendance = (clone $attended)->whereYear('date', $curYear)->whereMonth('date', $curMonth)->get();

        if (Carbon::parse($this->hired_on)->year < $curYear) {
            $totalAttendanceSoFar = (clone $attended)->whereYear('date', $curYear)
                ->whereDate('date', '<=', $now)->count();

            $totalAbsentedSoFar = (clone $absented)->whereYear('date', $curYear)
                    ->whereDate('date', '<=', $now)->count();
        } else {
            $totalAttendanceSoFar = (clone $attended)->whereDate('date', '<=', $now)->count();
            $totalAbsentedSoFar = (clone $absented)->whereDate('date', '<=', $now)->count();
        }
        $actualHours =
            $monthAttendance->sum(function ($attendance) {
                $signInTime = Carbon::parse($attendance->sign_in_time);
                $signOffTime = Carbon::parse($attendance->sign_off_time);
                return $signInTime->diffInMinutes($signOffTime) / 60;
            });


        $shiftHours = $this->activeShiftPeriod();
        $regularDutyHours = $this->activeShift()?->regular_duty_hours ?? 8.0;
        $effectiveHours = min($shiftHours, $regularDutyHours);
        
        $expectedHours = $workingDays * $effectiveHours;
        $expectedHoursSoFar = $workingDaysSoFar * $effectiveHours;

        return [
            "YearStats" => $this->getYearStats($globalSettings),

            "attendableThisMonth" => $workingDays,
            "holidaysThisMonth" => $holidaysCount,
            "weekendsThisMonth" => $weekendsCount,
            "attendedThisMonth" => $monthAttendance->count(),
            "absentedThisMonth" => $this->getAbsented()->whereMonth('date', $curMonth)->count(),

            "totalAttendanceThisYear" => $totalAttendanceSoFar,
            "totalAbsenceThisYear" => $workDaysSoFarThisYear - $totalAttendanceSoFar,

            "totalAttendanceSoFar" => $totalAttendanceSoFar,
            "totalAbsenceSoFar" => $totalAbsentedSoFar,

            "expectedHoursThisMonth" => $expectedHours,
            "actualHoursThisMonth" => $actualHours,
            "hoursDifference" => $actualHours - $expectedHours,
            "hoursDifferenceSoFar" => $actualHours - $expectedHoursSoFar,
        ];
    }

    public function monthHours($year, $month): array
    {
        $monthEnd = Carbon::createFromDate($year, $month, 1)->endOfMonth()->format('j');
        $commonServices = new \App\Services\CommonServices();
        $monthDates = [$year, $month, 1, $year, $month, $monthEnd];

        $globalSettings = Globals::first();
        $weekendOffDays = [$this->weekly_off_day];

        // Calculations for the entire month
        $holidaysCount = $commonServices->countHolidays($this->hired_on, $monthDates);
        $workingDays = $monthEnd - $holidaysCount -
            $commonServices->calcOffDays($weekendOffDays, $this->hired_on, $monthDates);

        $attended = $this->getAttended();

        $monthAttendance = (clone $attended)->whereYear('date', $year)->whereMonth('date', $month)->get();

        $actualHours =
            $monthAttendance->sum(function ($attendance) {
                $signInTime = Carbon::parse($attendance->sign_in_time);
                $signOffTime = Carbon::parse($attendance->sign_off_time);
                return $signInTime->diffInMinutes($signOffTime) / 60;
            });

        $shiftHours = $this->activeShiftPeriod();
        $regularDutyHours = $this->activeShift()?->regular_duty_hours ?? 8.0;
        $expectedHours = $workingDays * min($shiftHours, $regularDutyHours);

        return [
            "expectedHours" => $expectedHours,
            "actualHours" => $actualHours,
            "hoursDifference" => $actualHours - $expectedHours,
        ];
    }

    public function periodHours($startDate, $endDate): array
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        $commonServices = new \App\Services\CommonServices();
        $periodDates = [$start->year, $start->month, $start->day, $end->year, $end->month, $end->day];

        $globalSettings = Globals::first();
        $weekendOffDays = [$this->weekly_off_day];

        $totalDays = $start->diffInDays($end) + 1;

        $holidaysCount = $commonServices->countHolidays($this->hired_on, $periodDates);
        $workingDays = $totalDays - $holidaysCount -
            $commonServices->calcOffDays($weekendOffDays, $this->hired_on, $periodDates);

        $attended = $this->getAttended();

        $periodAttendance = (clone $attended)->whereBetween('date', [$start->toDateString(), $end->toDateString()])->get();

        $actualHours =
            $periodAttendance->sum(function ($attendance) {
                $signInTime = Carbon::parse($attendance->sign_in_time);
                $signOffTime = Carbon::parse($attendance->sign_off_time);
                return $signInTime->diffInMinutes($signOffTime) / 60;
            });

        $shiftHours = $this->activeShiftPeriod();
        $regularDutyHours = $this->activeShift()?->regular_duty_hours ?? 8.0;
        $expectedHours = $workingDays * min($shiftHours, $regularDutyHours);

        return [
            "expectedHours" => $expectedHours,
            "actualHours" => $actualHours,
            "hoursDifference" => $actualHours - $expectedHours,
        ];
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function advancePayments()
    {
        return $this->hasMany(AdvancePayment::class);
    }
}
