<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class StockyUser extends Authenticatable
{
    use HasApiTokens, Notifiable;

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'stocky';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'firstname',
        'lastname',
        'username',
        'email',
        'password',
        'phone',
        'status',
        'avatar',
        'role_id',
        'is_all_warehouses',
        'is_super_admin'
    ];

    /**
     * Check if user is an Administrator or Owner in Stocky.
     *
     * @return bool
     */
    public function isAdministrator()
    {
        $roleId = $this->role_id;
        $isSuperAdmin = $this->is_super_admin;

        \Illuminate\Support\Facades\Log::info("StockyUser::isAdministrator check for user id: {$this->id}", [
            'role_id' => $roleId,
            'is_super_admin' => $isSuperAdmin
        ]);

        if ($roleId == 1 || $isSuperAdmin == 1 || $isSuperAdmin === true) {
            \Illuminate\Support\Facades\Log::info("StockyUser::isAdministrator returned true via column checks");
            return true;
        }

        // Query the roles associated via the role_user table in the stocky connection
        try {
            $hasAdminRole = \Illuminate\Support\Facades\DB::connection('stocky')
                ->table('role_user')
                ->join('roles', 'role_user.role_id', '=', 'roles.id')
                ->where('role_user.user_id', $this->id)
                ->whereIn('roles.name', ['Admin', 'Owner', 'admin', 'owner'])
                ->exists();

            \Illuminate\Support\Facades\Log::info("StockyUser::isAdministrator pivot table check result: " . ($hasAdminRole ? 'true' : 'false'));
            return $hasAdminRole;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('StockyUser::isAdministrator role check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Accessor for name attribute to combine first and last name.
     */
    public function getNameAttribute()
    {
        $fullName = trim(($this->firstname ?? '') . ' ' . ($this->lastname ?? ''));
        return !empty($fullName) ? $fullName : ($this->username ?? 'Stocky User');
    }

    /**
     * Accessor for hired_on attribute mapped to created_at.
     */
    public function getHiredOnAttribute()
    {
        return $this->created_at ? \Carbon\Carbon::parse($this->created_at)->toDateString() : \Carbon\Carbon::now()->toDateString();
    }

    /**
     * Accessor for is_remote attribute.
     */
    public function getIsRemoteAttribute()
    {
        return false;
    }

    /**
     * Accessor for address attribute.
     */
    public function getAddressAttribute()
    {
        return null;
    }

    /**
     * Accessor for bank_acc_no attribute.
     */
    public function getBankAccNoAttribute()
    {
        return null;
    }

    /**
     * Spatie-like role checker for custom integration.
     */
    public function hasRole($role)
    {
        if ($role === 'admin') {
            return $this->isAdministrator();
        }
        if ($role === 'employee') {
            return !$this->isAdministrator();
        }
        return false;
    }

    /**
     * Spatie-like hasAnyRole checker.
     */
    public function hasAnyRole($roles)
    {
        if (is_string($roles)) {
            $roles = explode('|', $roles);
        } elseif (is_array($roles)) {
            // Keep as array
        } else {
            $roles = func_get_args();
            if (isset($roles[0]) && is_array($roles[0])) {
                $roles = $roles[0];
            }
        }

        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Spatie-like permission checker.
     */
    public function hasPermissionTo($permission, $guardName = null)
    {
        return $this->isAdministrator();
    }

    /**
     * Get the names of the user's roles.
     */
    public function getRoleNames()
    {
        return collect([$this->isAdministrator() ? 'admin' : 'employee']);
    }

    /**************------- RELATIONSHIPS (to local HRM database) -------*************/

    public function salaries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmployeeSalary::class, 'employee_id');
    }

    public function payrolls(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Payroll::class, 'employee_id');
    }

    public function evaluations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmployeeEvaluation::class, 'employee_id');
    }

    public function employeePositions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmployeePosition::class, 'employee_id');
    }

    public function department(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Department::class, 'id', 'department_id');
    }

    public function manages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Manager::class, 'employee_id');
    }

    public function attendances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Attendance::class, 'employee_id');
    }

    public function employeeShifts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmployeeShift::class, 'employee_id');
    }

    /**************------- HELPER METHODS -------*************/

    public function salary()
    {
        $salary = $this->salaries()->where('end_date', null)->first();
        if (!$salary) {
            return ['USD', 0, $this->hired_on ?? now()->toDateString()];
        }
        return [$salary->currency, $salary->monthly_salary, $salary->start_date];
    }

    public function activePosition()
    {
        $position = $this->employeePositions()->where('end_date', null)->first();
        return $position ? $position->get() : collect();
    }

    public function activePositions(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->employeePositions()->where('end_date', null)->get();
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

    public function getAttended(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->attendances()->whereNotIn('status', ['missed', 'absent']);
    }

    public function getAttendedInYear($year): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->attendances()->whereNotIn('status', ['missed', 'absent'])->whereYear('date', $year);
    }

    public function getAbsentedInYear($year): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->attendances()->whereIn('status', ['missed', 'absent'])->whereYear('date', $year);
    }

    public function getAbsented(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->attendances()->whereIn('status', ['missed', 'absent']);
    }

    public function getYearStats($globalSettings = null)
    {
        $globalSettings = $globalSettings ?? Globals::first();
        $weekendOffDays = [$this->weekly_off_day];
        $commonServices = new \App\Services\CommonServices();
        $thisYearData = $commonServices->calcOffDays($weekendOffDays, $this->hired_on);
        $holidaysThisYear = $commonServices->countHolidays($this->hired_on);
        return [
            "workingDaysThisYear" => $thisYearData['total_year_days'],
            "WeekendOffDaysThisYear" => $thisYearData['offDays'],
            "weekendOffDays" => $weekendOffDays,
            "HolidaysThisYear" => $holidaysThisYear,
            "absence_limit" => $globalSettings ? $globalSettings->absence_limit : 30,
        ];
    }

    public function myStats(): array
    {
        $now = \Carbon\CarbonImmutable::now();
        $curDay = $now->day;
        $curMonth = $now->month;
        $curYear = $now->year;
        $monthEnd = $now->endOfMonth()->format('j');
        $globalSettings = Globals::first();
        $weekendOffDays = [$this->weekly_off_day];
        $commonServices = new \App\Services\CommonServices();
        $monthDates = [$curYear, $curMonth, 1, $curYear, $curMonth, $monthEnd];

        $holidaysCount = $commonServices->countHolidays($this->hired_on, $monthDates);
        $weekendsCount = $commonServices->calcOffDays($weekendOffDays, $this->hired_on, $monthDates);
        $workingDays = $monthEnd - $holidaysCount - $weekendsCount;

        $holidaysCountSoFar = $commonServices->countHolidays($this->hired_on, [$curYear, $curMonth, 1, $curYear, $curMonth, $curDay]);
        $workingDaysSoFar = $curDay - 1 - $holidaysCountSoFar - $commonServices->calcOffDays($weekendOffDays, $this->hired_on, [$curYear, $curMonth, 1, $curYear, $curMonth, $curDay]);

        $workDaysSoFarThisYear = $now->startOfYear()->diffInDays($now) - $commonServices->countHolidays($this->hired_on, [$curYear, 1, 1, $curYear, $curMonth, $curDay]) - $commonServices->calcOffDays($weekendOffDays, $this->hired_on, [$curYear, 1, 1, $curYear, $curMonth, $curDay]);

        $attended = $this->getAttended();
        $absented = $this->getAbsented();

        $monthAttendance = (clone $attended)->whereYear('date', $curYear)->whereMonth('date', $curMonth)->get();

        if (\Carbon\Carbon::parse($this->hired_on)->year < $curYear) {
            $totalAttendanceSoFar = (clone $attended)->whereYear('date', $curYear)->whereDate('date', '<=', $now)->count();
            $totalAbsentedSoFar = (clone $absented)->whereYear('date', $curYear)->whereDate('date', '<=', $now)->count();
        } else {
            $totalAttendanceSoFar = (clone $attended)->whereDate('date', '<=', $now)->count();
            $totalAbsentedSoFar = (clone $absented)->whereDate('date', '<=', $now)->count();
        }
        $actualHours = $monthAttendance->sum(function ($attendance) {
            $signInTime = \Carbon\Carbon::parse($attendance->sign_in_time);
            $signOffTime = \Carbon\Carbon::parse($attendance->sign_off_time);
            return $signInTime->diffInMinutes($signOffTime) / 60;
        });

        $shiftHours = $this->activeShiftPeriod();
        $expectedHours = $workingDays * $shiftHours;
        $expectedHoursSoFar = $workingDaysSoFar * $shiftHours;

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
        $monthEnd = \Carbon\Carbon::createFromDate($year, $month, 1)->endOfMonth()->format('j');
        $commonServices = new \App\Services\CommonServices();
        $monthDates = [$year, $month, 1, $year, $month, $monthEnd];

        $globalSettings = Globals::first();
        $weekendOffDays = [$this->weekly_off_day];

        $holidaysCount = $commonServices->countHolidays($this->hired_on, $monthDates);
        $workingDays = $monthEnd - $holidaysCount - $commonServices->calcOffDays($weekendOffDays, $this->hired_on, $monthDates);

        $attended = $this->getAttended();
        $monthAttendance = (clone $attended)->whereYear('date', $year)->whereMonth('date', $month)->get();

        $actualHours = $monthAttendance->sum(function ($attendance) {
            $signInTime = \Carbon\Carbon::parse($attendance->sign_in_time);
            $signOffTime = \Carbon\Carbon::parse($attendance->sign_off_time);
            return $signInTime->diffInMinutes($signOffTime) / 60;
        });

        $shiftHours = $this->activeShiftPeriod();
        $expectedHours = $workingDays * $shiftHours;

        return [
            "expectedHours" => $expectedHours,
            "actualHours" => $actualHours,
            "hoursDifference" => $actualHours - $expectedHours,
        ];
    }
}

