<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        if (!isAdmin()) {
            // Don't expose leave requests to non-admins other than the employee's.
            $leaveRequests = \App\Models\Request::with('employee')
                ->where('status', 1)
                ->where('employee_id', '=', auth()->user()->id)
                ->get();
        } else {
            $leaveRequests = \App\Models\Request::with('employee')
                ->where('status', 1)
                ->get();
        }

        return Inertia::render('Dashboard', [
            'calendarItems' => \App\Models\Calendar::get(),
            'leaveRequests' => $leaveRequests,
        ]);
    }

    public function reportsIndex()
    {
        $month = (int)request('month', date('n'));
        $year = (int)request('year', date('Y'));
        $employeeId = request('employee_id', 'all');

        // Fetch all employees for filter dropdown
        $employees = \App\Models\Employee::select('id', 'name')->orderBy('name')->get();

        if ($employeeId !== 'all') {
            $employee = \App\Models\Employee::find($employeeId);
            if ($employee) {
                // Calculate monthly stats for specific employee
                $monthHoursData = $employee->monthHours($year, $month);
                
                $attendedDays = $employee->attendances()
                    ->whereYear('date', $year)
                    ->whereMonth('date', $month)
                    ->whereNotIn('status', ['missed', 'absent'])
                    ->count();

                $lateCount = 0;
                try {
                    $lateCount = $employee->attendances()
                        ->whereYear('date', $year)
                        ->whereMonth('date', $month)
                        ->where('status', 'late')
                        ->count();
                } catch (\Exception $e) {
                    try {
                        $lateCount = $employee->attendances()
                            ->whereYear('date', $year)
                            ->whereMonth('date', $month)
                            ->where('is_late', true)
                            ->count();
                    } catch (\Exception $ex) {
                        $lateCount = 1;
                    }
                }

                $shiftPeriod = $employee->activeShiftPeriod();
                $workingDays = $shiftPeriod > 0 ? ($monthHoursData['expectedHours'] / $shiftPeriod) : 22;
                $attendanceRate = $workingDays > 0 ? round(($attendedDays / $workingDays) * 100) : 100;
                if ($attendanceRate > 100) $attendanceRate = 100;

                $stats = [
                    'empCount' => 1,
                    'deptCount' => $employee->department_id ? 1 : 0,
                    'attendanceRate' => $attendanceRate,
                    'lateCount' => $lateCount,
                    'expectedHours' => round($monthHoursData['expectedHours'], 1),
                    'actualHours' => round($monthHoursData['actualHours'], 1),
                    'employeeName' => $employee->name,
                ];
            } else {
                $stats = [
                    'empCount' => 0,
                    'deptCount' => 0,
                    'attendanceRate' => 0,
                    'lateCount' => 0,
                    'expectedHours' => 0,
                    'actualHours' => 0,
                    'employeeName' => 'Unknown',
                ];
            }
        } else {
            // General stats
            $empCount = \App\Models\Employee::count();
            $deptCount = \App\Models\StockyDepartment::count();

            $attendedDays = \App\Models\Attendance::whereYear('date', $year)
                ->whereMonth('date', $month)
                ->whereNotIn('status', ['missed', 'absent'])
                ->count();

            $lateCount = \App\Models\Attendance::whereYear('date', $year)
                ->whereMonth('date', $month)
                ->where('status', 'late')
                ->count();

            $attendanceRate = 0;
            if ($empCount > 0) {
                $expectedLogs = $empCount * 22;
                $attendanceRate = round(($attendedDays / $expectedLogs) * 100);
                if ($attendanceRate > 100) $attendanceRate = 100;
            }

            $stats = [
                'empCount' => $empCount,
                'deptCount' => $deptCount,
                'attendanceRate' => $attendanceRate,
                'lateCount' => $lateCount,
                'expectedHours' => $empCount * 22 * 8,
                'actualHours' => $attendedDays * 8,
                'employeeName' => 'All Employees',
            ];
        }

        $payrollQuery = \App\Models\Payroll::query()
            ->whereYear('due_date', $year)
            ->whereMonth('due_date', $month);

        $attendanceQuery = \App\Models\Attendance::query()
            ->whereYear('date', $year)
            ->whereMonth('date', $month);

        $lateQuery = \App\Models\Attendance::query()
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->where('status', 'late');

        $loansQuery = \App\Models\Loan::query()
            ->whereYear('date', $year)
            ->whereMonth('date', $month);

        $cashTxQuery = \App\Models\CashTransaction::query()
            ->whereYear('date', $year)
            ->whereMonth('date', $month);

        $advQuery = \App\Models\AdvancePayment::query()
            ->whereYear('date', $year)
            ->whereMonth('date', $month);

        if ($employeeId !== 'all') {
            $payrollQuery->where('employee_id', $employeeId);
            $attendanceQuery->where('employee_id', $employeeId);
            $lateQuery->where('employee_id', $employeeId);
            $loansQuery->where('employee_id', $employeeId);
            $cashTxQuery->where('employee_id', $employeeId);
            $advQuery->where('employee_id', $employeeId);
        }

        $payrollCount = (clone $payrollQuery)->count();
        $attendanceCount = (clone $attendanceQuery)->count();
        $lateCountForReports = (clone $lateQuery)->count();
        $loansCount = (clone $loansQuery)->count();
        $cashTxCount = (clone $cashTxQuery)->count();
        $advCount = (clone $advQuery)->count();

        $payrollLatest = (clone $payrollQuery)->latest('updated_at')->value('updated_at');
        $attendanceLatest = (clone $attendanceQuery)->latest('updated_at')->value('updated_at');
        $lateLatest = (clone $lateQuery)->latest('updated_at')->value('updated_at');
        $loansLatest = (clone $loansQuery)->latest('updated_at')->value('updated_at');
        $cashTxLatest = (clone $cashTxQuery)->latest('updated_at')->value('updated_at');
        $advLatest = (clone $advQuery)->latest('updated_at')->value('updated_at');

        $generatedReports = [
            [
                'id' => 'attendance',
                'name' => 'Monthly Attendance Summary',
                'format' => 'CSV/PDF',
                'created_at' => $attendanceLatest ? Carbon::parse($attendanceLatest)->format('Y-m-d H:i') : null,
                'has_data' => $attendanceCount > 0,
                'records' => $attendanceCount,
                'download_url' => route('reports.attendance', array_merge(request()->only(['month', 'year', 'employee_id']), ['format' => 'csv'])),
            ],
            [
                'id' => 'payroll',
                'name' => 'Payroll Breakdown Report',
                'format' => 'CSV/PDF',
                'created_at' => $payrollLatest ? Carbon::parse($payrollLatest)->format('Y-m-d H:i') : null,
                'has_data' => $payrollCount > 0,
                'records' => $payrollCount,
                'download_url' => route('reports.payroll', array_merge(request()->only(['month', 'year', 'employee_id']), ['format' => 'csv'])),
            ],
            [
                'id' => 'late',
                'name' => 'Late Entry / Early Exit Details',
                'format' => 'CSV/PDF',
                'created_at' => $lateLatest ? Carbon::parse($lateLatest)->format('Y-m-d H:i') : null,
                'has_data' => $lateCountForReports > 0,
                'records' => $lateCountForReports,
                'download_url' => route('reports.late', array_merge(request()->only(['month', 'year', 'employee_id']), ['format' => 'csv'])),
            ],
            [
                'id' => 'loans',
                'name' => 'Employee Loans Report',
                'format' => 'CSV',
                'created_at' => $loansLatest ? Carbon::parse($loansLatest)->format('Y-m-d H:i') : null,
                'has_data' => $loansCount > 0,
                'records' => $loansCount,
                'download_url' => route('reports.loans', array_merge(request()->only(['month', 'year', 'employee_id']), ['format' => 'csv'])),
            ],
            [
                'id' => 'cash-transactions',
                'name' => 'Cash Transactions Report',
                'format' => 'CSV',
                'created_at' => $cashTxLatest ? Carbon::parse($cashTxLatest)->format('Y-m-d H:i') : null,
                'has_data' => $cashTxCount > 0,
                'records' => $cashTxCount,
                'download_url' => route('reports.cash_transactions', array_merge(request()->only(['month', 'year', 'employee_id']), ['format' => 'csv'])),
            ],
            [
                'id' => 'advance-payments',
                'name' => 'Advance Payments Report',
                'format' => 'CSV',
                'created_at' => $advLatest ? Carbon::parse($advLatest)->format('Y-m-d H:i') : null,
                'has_data' => $advCount > 0,
                'records' => $advCount,
                'download_url' => route('reports.advance_payments', array_merge(request()->only(['month', 'year', 'employee_id']), ['format' => 'csv'])),
            ],
        ];

        return Inertia::render('Report/Reports', [
            'stats' => $stats,
            'employees' => $employees,
            'generatedReports' => $generatedReports,
            'filters' => [
                'month' => $month,
                'year' => $year,
                'employee_id' => $employeeId,
            ]
        ]);
    }
}
