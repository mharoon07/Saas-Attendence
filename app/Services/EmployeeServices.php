<?php

namespace App\Services;

use App\Mail\EmployeeRegisterationCredentials;
use App\Models\ArchivedEmployee;
use App\Models\Employee;
use App\Models\EmployeePosition;
use App\Models\EmployeeSalary;
use App\Models\EmployeeShift;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class EmployeeServices
{
    public function createEmployee($res): \Illuminate\Http\RedirectResponse
    {
        if (is_null($res['hired_on'])) {
            $res['hired_on'] = now()->format('Y-m-d');
        }
        $password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
        $res['password'] = bcrypt($password);
        if (empty($res['device_employee_id'])) {
            $res['device_employee_id'] = Employee::getNextEmployeeId();
        }

        $emp = Employee::create([
            'name' => $res['name'],
            'email' => $res['email'],
            'phone' => $res['phone'],
            'national_id' => $res['national_id'],
            'address' => $res['address'],
            'bank_acc_no' => $res['bank_acc_no'],
            'hired_on' => $res['hired_on'],
            'branch_id' => $res['branch_id'],
            'department_id' => $res['department_id'],
            'weekly_off_day' => $res['weekly_off_day'],
            'password' => $res['password'],
            'device_employee_id' => $res['device_employee_id'],
        ]);

        // Salary Registration
        EmployeeSalary::create([
            'employee_id' => $emp['id'],
            'currency' => $res['currency'],
            'salary_type' => $res['salary_type'] ?? 'monthly',
            'hourly_rate' => $res['hourly_rate'] ?? null,
            'monthly_salary' => $res['monthly_salary'],
            'daily_salary' => $res['monthly_salary'] / 30,
            'overtime_rate' => $res['overtime_rate'],
            'custom_additions' => $res['custom_additions'] ?? null,
            'custom_deductions' => $res['custom_deductions'] ?? null,
            'start_date' => Carbon::createFromFormat('Y-m-d', $res['hired_on'])->addMonth()->startOfMonth(),
            'end_date' => null,
        ]);

        // Assign Position
        if (isset($res['position_id'])) {
            EmployeePosition::create([
                'employee_id' => $emp['id'],
                'position_id' => $res['position_id'],
                'start_date' => Carbon::createFromFormat('Y-m-d', $res['hired_on']),
                'end_date' => null,
            ]);
        }

        // Assign Shift
        if (isset($res['shift_id'])) {
            EmployeeShift::create([
                'employee_id' => $emp['id'],
                'shift_id' => $res['shift_id'],
                'start_date' => Carbon::createFromFormat('Y-m-d', $res['hired_on']),
                'end_date' => null,
            ]);
        }

        // Assign default role
        if (!\Spatie\Permission\Models\Role::where('name', 'employee')->where('guard_name', 'web')->exists()) {
            \Spatie\Permission\Models\Role::create(['name' => 'employee', 'guard_name' => 'web']);
        }
        $emp->assignRole('employee');

        // Send Email to user with credentials
        try {
            Mail::to($emp->email)->send(new EmployeeRegisterationCredentials([
                'name' => $emp->name,
                'email' => $emp->email,
                'password' => $password,
            ]));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Failed to send registration email to " . $emp->email . ": " . $e->getMessage());
        }

        return to_route('employees.show', ['employee' => $emp->id]);
    }

    public function updateEmployee($employee, $res): \Illuminate\Http\RedirectResponse
    {

        // Update Personal Details
        $employee->update([
            'name' => $res['name'],
            'email' => $res['email'],
            'phone' => $res['phone'],
            'national_id' => $res['national_id'],
            'address' => $res['address'],
            'bank_acc_no' => $res['bank_acc_no'],
            'hired_on' => $res['hired_on'],
            'branch_id' => $res['branch_id'],
            'department_id' => $res['department_id'],
            'weekly_off_day' => $res['weekly_off_day'],
            'device_employee_id' => !empty($res['device_employee_id']) ? $res['device_employee_id'] : null,
        ]);

        // Update Shifts, Salary, Position, and Permissions
        $curPos = $employee->employeePositions()->whereNull('end_date')->first();
        if ($curPos->position_id != $res['position_id']) {
            $curPos->update([
                'end_date' => Carbon::now()->format('Y-m-d'),
            ]);
            $employee->employeePositions()->create([
                'employee_id' => $employee->id,
                'position_id' => $res['position_id'],
                'start_date' => Carbon::now()->format('Y-m-d'),
                'end_date' => null,
            ]);
        }

        $curShift = $employee->employeeShifts()->whereNull('end_date')->first();
        if ($curShift->shift_id != $res['shift_id']) {
            $curShift->update([
                'end_date' => Carbon::now()->format('Y-m-d'),
            ]);
            $employee->employeeShifts()->create([
                'employee_id' => $employee->id,
                'shift_id' => $res['shift_id'],
                'start_date' => Carbon::now()->format('Y-m-d'),
                'end_date' => null,
            ]);
        }

        $curSalary = $employee->salaries()->whereNull('end_date')->first();
        $newSalaryType = $res['salary_type'] ?? 'monthly';
        $newHourlyRate = $res['hourly_rate'] ?? null;
        if (
            !$curSalary ||
            $curSalary->monthly_salary != $res['monthly_salary'] ||
            $curSalary->overtime_rate != $res['overtime_rate'] ||
            $curSalary->currency != $res['currency'] ||
            ($curSalary->salary_type ?? 'monthly') != $newSalaryType ||
            $curSalary->hourly_rate != $newHourlyRate ||
            json_encode($curSalary->custom_additions) !== json_encode($res['custom_additions'] ?? null) ||
            json_encode($curSalary->custom_deductions) !== json_encode($res['custom_deductions'] ?? null)
        ) {
            if ($curSalary) {
                $curSalary->update([
                    'end_date' => Carbon::now()->format('Y-m-d'),
                ]);
            }
            $employee->salaries()->create([
                'employee_id' => $employee->id,
                'currency' => $res['currency'],
                'salary_type' => $newSalaryType,
                'hourly_rate' => $newHourlyRate,
                'monthly_salary' => $res['monthly_salary'],
                'daily_salary' => $res['monthly_salary'] / 30,
                'overtime_rate' => $res['overtime_rate'],
                'custom_additions' => $res['custom_additions'] ?? null,
                'custom_deductions' => $res['custom_deductions'] ?? null,
                'start_date' => Carbon::now()->format('Y-m-d'),
                'end_date' => null,
            ]);
        }

        $currentRole = $employee->getRoleNames()->first();
        if ($currentRole != $res['role']) {
            if ($currentRole) {
                $employee->removeRole($currentRole);
            }
            if (!\Spatie\Permission\Models\Role::where('name', $res['role'])->where('guard_name', 'web')->exists()) {
                \Spatie\Permission\Models\Role::create(['name' => $res['role'], 'guard_name' => 'web']);
            }
            $employee->assignRole($res['role']);
            $employee->save();
        }
        return to_route('employees.show', ['employee' => $employee->id]);
    }

    public function deleteEmployee($id): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
    {

        $employee = Employee::findOrFail($id);

        if ($employee->id == auth()->user()->id) {
            return response()->json(['Error' => 'You cannot delete yourself.'], 403);
        }

        // Move employee to archived_employees first..
        ArchivedEmployee::create([
            'name' => $employee->name,
            'phone' => $employee->phone,
            'email' => $employee->email,
            'national_id' => $employee->national_id,
            'address' => $employee->address,
            'bank_acc_no' => $employee->bank_acc_no,
            'hired_on' => $employee->hired_on,
            'released_on' => Carbon::now()->format('Y-m-d'),
        ]);

        // Delete employee
        $employee->delete();

        return to_route('employees.index');
    }
}
