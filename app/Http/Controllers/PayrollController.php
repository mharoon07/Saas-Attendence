<?php

namespace App\Http\Controllers;

use App\Models\Globals;
use App\Models\Metric;
use App\Models\Payroll;
use App\Services\CommonServices;
use App\Services\PayrollServices;
use App\Services\ValidationServices;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PayrollController extends Controller
{
    protected PayrollServices $payrollServices;
    protected ValidationServices $validationServices;
    public function __construct()
    {
        $this->payrollServices = new PayrollServices;
        $this->validationServices = new ValidationServices;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->validationServices->validatePayrollIndexParams($request);

        // Setting up filters, if any.
        $dateParam = $request->input('date', '');
        $statusParam = $request->input('status', '');
        $departmentParam = $request->input('department_id', '');
        $shiftParam = $request->input('shift_id', '');
        $employeeParam = $request->input('employee_id', '');

        if ($dateParam) {
            $date = Carbon::createFromDate($request->date['year'], $request->date['month'], Carbon::now()->startOfMonth()->format('j'));
            if ($date->isAfter(Carbon::today())) {
                return response()->json(['Error' => 'Date cannot be in the future. Go back and choose a date before today.'], 400);
            }
            $date = $date->toDateString();
        } else {
            $date = '';
        }

        // Main Query
        $payrolls = Payroll::leftJoin('employees', 'payrolls.employee_id', '=', 'employees.id')
            ->select(
                'payrolls.id', 'payrolls.due_date', 'payrolls.currency', 'payrolls.total_payable', 
                'employees.name as employee_name', 'employees.device_employee_id', 'employees.id as emp_id',
                'payrolls.status', 'payrolls.is_reviewed',
                'payrolls.monthly_salary', 'payrolls.daily_salary', 'payrolls.regular_working_days',
                'payrolls.absent_days', 'payrolls.leave_days', 'payrolls.overtime_hours', 'payrolls.overtime_amount',
                'payrolls.total_additions', 'payrolls.total_deductions', 'payrolls.gross_salary', 'payrolls.net_salary',
                'payrolls.payroll_month', 'payrolls.payroll_year'
            )
            ->orderBy('payrolls.id', 'desc');

        // Limit to logged-in employee if not admin
        if (!isAdmin())
            $payrolls->where('payrolls.employee_id', auth()->user()->id);

        // Department Filter
        if ($departmentParam) {
            $payrolls->where('employees.department_id', $departmentParam);
        }

        // Shift Filter
        if ($shiftParam) {
            $payrolls->whereIn('payrolls.employee_id', function($q) use ($shiftParam) {
                $q->select('employee_id')
                  ->from('employee_shifts')
                  ->where('shift_id', $shiftParam)
                  ->whereNull('end_date');
            });
        }

        // Employee Filter
        if ($employeeParam) {
            $payrolls->where('payrolls.employee_id', $employeeParam);
        }

        // Date Filter (using new month/year structure if available)
        if ($date) {
            $payrolls->where(function($q) use ($request) {
                $q->where(function($q2) use ($request) {
                    $q2->where('payrolls.payroll_year', $request->date['year'])
                       ->where('payrolls.payroll_month', $request->date['month'] + 1);
                })->orWhere(function($q2) use ($request) {
                    $q2->whereNull('payrolls.payroll_month')
                       ->whereYear('payrolls.due_date', $request->date['year'])
                       ->whereMonth('payrolls.due_date', $request->date['month'] + 1);
                });
            });
        }

        $tabParam = $request->input('tab', 'current');
        if (!in_array($tabParam, ['current', 'previous'])) {
            $tabParam = 'current';
        }

        // Current vs Previous Tab Filter
        if ($tabParam === 'previous') {
            $payrolls->where('payrolls.is_current', false);
        } else {
            $payrolls->where('payrolls.is_current', true);
        }

        // Status Filter
        if ($statusParam == 'pending') {
            $payrolls->where('payrolls.status', false)->where('payrolls.is_reviewed', false);
        } else if ($statusParam == 'reviewed') {
            $payrolls->where('payrolls.status', false)->where('payrolls.is_reviewed', true);
        } else if ($statusParam == 'paid') {
            $payrolls->where('payrolls.status', true);
        }

        return Inertia::render('Payroll/Payrolls', [
            'payrolls' => $payrolls->paginate(config('constants.data.pagination_count'))->withQueryString(),
            "dateParam" => $date,
            "statusParam" => $statusParam,
            "departmentParam" => $departmentParam,
            "shiftParam" => $shiftParam,
            "employeeParam" => $employeeParam,
            "tabParam" => $tabParam,
            "employees" => \App\Models\Employee::with(['employeeShifts' => function($q) {
                $q->whereNull('end_date');
            }])
            ->get()
            ->map(function($emp) {
                $activeShift = $emp->employeeShifts->first();
                return [
                    'id' => $emp->id,
                    'name' => $emp->name,
                    'device_employee_id' => $emp->device_employee_id,
                    'employee_code' => $emp->employee_code,
                    'department_id' => $emp->department_id,
                    'shift_id' => $activeShift ? $activeShift->shift_id : null,
                ];
            }),
            "shifts" => \App\Models\Shift::select('id', 'name')->orderBy('name')->get(),
            "departments" => \App\Models\StockyDepartment::select(['id', 'department as name'])->orderBy('department')->get(),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        authenticateIfNotAdmin(auth()->user()->id, $id);
        $payroll = Payroll::with('employee')->find($id);
        if ($payroll) {
            return Inertia::render('Payroll/PayrollView', [
                'payroll' => $payroll,
            ]);
        } else {
            return redirect()->back()->withErrors(['end_of_payrolls' => 'No More Payrolls to Show']);
        }
    }

    /**
     * Payroll Review Page.
     */
    public function edit(string $id)
    {
        $payroll = Payroll::with(['employee', 'additions', 'deductions'])->findOrFail($id);
        $commonServices = new CommonServices();

        if (!$payroll->employee) {
            return redirect()->route('payrolls.index')->withErrors(['error' => 'Employee associated with this payroll not found.']);
        }

        $startDate = Carbon::parse($payroll->period_start ?? $payroll->due_date)->startOfMonth();
        $endDate = Carbon::parse($payroll->period_end ?? $payroll->due_date);

        // Fetch explicit attendance records for this period
        $existingAttendances = \App\Models\Attendance::where('employee_id', $payroll->employee->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->keyBy('date');

        // Fetch approved leave records for this period
        $approvedLeaves = \App\Models\Leave::where('employee_id', $payroll->employee->id)
            ->where('status', 'Approved')
            ->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate->toDateString(), $endDate->toDateString()])
                  ->orWhereBetween('end_date', [$startDate->toDateString(), $endDate->toDateString()])
                  ->orWhere(function($sub) use ($startDate, $endDate) {
                      $sub->where('start_date', '<=', $startDate->toDateString())
                          ->where('end_date', '>=', $endDate->toDateString());
                  });
            })
            ->get();

        $weekendOffDay = strtolower($payroll->employee->weekly_off_day ?? 'sunday');

        $fullAttendancesList = [];
        $attendedCount = 0;
        $absentedCount = 0;
        $lateCount = 0;
        $leaveDaysCount = 0;

        $periodCursor = $startDate->copy();
        while ($periodCursor->lte($endDate)) {
            $dateStr = $periodCursor->toDateString();
            $dayOfWeek = strtolower($periodCursor->format('l'));

            // Check Priority 1: Approved Leave
            $isOnLeave = false;
            foreach ($approvedLeaves as $l) {
                if ($dateStr >= $l->start_date && $dateStr <= $l->end_date) {
                    $isOnLeave = true;
                    break;
                }
            }

            if ($isOnLeave) {
                $leaveDaysCount++;
                $fullAttendancesList[] = [
                    'id' => null,
                    'date' => $dateStr,
                    'status' => 'leave',
                    'sign_in_time' => null,
                    'sign_off_time' => null,
                ];
            } else if (isset($existingAttendances[$dateStr])) {
                $att = $existingAttendances[$dateStr];
                if ($att->status === 'on_time') {
                    $attendedCount++;
                } else if ($att->status === 'late') {
                    $attendedCount++;
                    $lateCount++;
                } else if (in_array($att->status, ['absent', 'missed'])) {
                    $absentedCount++;
                } else if ($att->status === 'leave') {
                    $leaveDaysCount++;
                } else {
                    $attendedCount++;
                }
                $fullAttendancesList[] = [
                    'id' => $att->id,
                    'date' => $dateStr,
                    'status' => $att->status,
                    'sign_in_time' => $att->sign_in_time,
                    'sign_off_time' => $att->sign_off_time,
                ];
            } else if ($dayOfWeek === $weekendOffDay) {
                $fullAttendancesList[] = [
                    'id' => null,
                    'date' => $dateStr,
                    'status' => 'weekly_off_day',
                    'sign_in_time' => null,
                    'sign_off_time' => null,
                ];
            } else {
                $absentedCount++;
                $fullAttendancesList[] = [
                    'id' => null,
                    'date' => $dateStr,
                    'status' => 'absent',
                    'sign_in_time' => null,
                    'sign_off_time' => null,
                ];
            }

            $periodCursor->addDay();
        }

        $month_stats = [
            'attendable_days' => $payroll->regular_working_days ?? max(0, count($fullAttendancesList) - $leaveDaysCount),
            'attended' => $attendedCount,
            'absented' => $payroll->absent_days ?? $absentedCount,
            'late' => $lateCount,
        ];
        $hours = $payroll->employee->periodHours($startDate->toDateString(), $endDate->toDateString());

        // Calculate pending loans and advance payments for this payroll
        $activeLoans = \App\Models\Loan::where('employee_id', $payroll->employee->id)
            ->where('status', 'active')
            ->get();
        
        $loanDeduction = 0;
        foreach ($activeLoans as $loan) {
            if ($loan->deduction_type === 'fixed' && (float)$loan->deduction_amount > 0) {
                $deduction = (float)$loan->deduction_amount;
            } else {
                $pct = ($loan->deduction_percentage && (float)$loan->deduction_percentage > 0) ? (float)$loan->deduction_percentage : 100;
                $deduction = (float)$loan->total_amount * ($pct / 100);
            }
            if ($deduction <= 0) {
                $deduction = (float)$loan->remaining_balance;
            }
            $loanDeduction += min($deduction, (float)$loan->remaining_balance);
        }

        $activeAdvances = \App\Models\AdvancePayment::where('employee_id', $payroll->employee->id)
            ->whereIn('status', ['approved', 'pending'])
            ->where('remaining_amount', '>', 0)
            ->get();
        
        $advancePaymentDeduction = (float)$activeAdvances->sum('remaining_amount');

        $additions = $payroll->additions ? $payroll->additions->toArray() : [
            'custom_items' => [],
            'extra_hour_rate' => 0,
        ];

        $deductions = $payroll->deductions ? $payroll->deductions->toArray() : [
            'custom_items' => [],
            'negative_hour_rate' => 0,
            'loan_deduction' => $loanDeduction,
            'advance_payment_deduction' => $advancePaymentDeduction,
        ];

        if ($payroll->deductions) {
            $deductions['loan_deduction'] = ((float)$payroll->deductions->loan_deduction > 0)
                ? (float)$payroll->deductions->loan_deduction
                : $loanDeduction;

            $deductions['advance_payment_deduction'] = ((float)$payroll->deductions->advance_payment_deduction > 0)
                ? (float)$payroll->deductions->advance_payment_deduction
                : $advancePaymentDeduction;
        }

        $incomeTax = Globals::select('income_tax')->first() ?? (object)['income_tax' => 14.0];

        return Inertia::render('Payroll/PayrollReview', [
            'payroll' => $payroll,
            "month_stats" => $month_stats,
            'additions' => $additions,
            'deductions' => $deductions,
            'income_tax' => $incomeTax,
            'hours' => $hours,
            'metrics' => Metric::where('created_at', '<=', $payroll->created_at)->get(),
            'attendances' => $fullAttendancesList,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'month_year.month' => 'required|integer',
            'month_year.year' => 'required|integer',
            'employee_id' => 'nullable|exists:employees,id',
            'shift_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
        ]);

        $year = $request->month_year['year'];
        $month = $request->month_year['month'] + 1; // VueDatePicker month is 0-indexed

        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = Carbon::create($year, $month, 1)->endOfMonth();
        $today = Carbon::today();

        $startDate = $monthStart->toDateString();

        // If generating for the current ongoing month, cap period_end at current date
        if ($monthStart->year == $today->year && $monthStart->month == $today->month && $today->lt($monthEnd)) {
            $endDate = $today->toDateString();
        } else {
            $endDate = $monthEnd->toDateString();
        }
        
        $employeeQuery = \App\Models\Employee::query();

        if ($request->filled('employee_id')) {
            $employeeQuery->where('id', $request->employee_id);
        }

        if ($request->filled('department_id')) {
            $employeeQuery->where('department_id', $request->department_id);
        }

        if ($request->filled('shift_id')) {
            $employeeQuery->whereHas('employeeShifts', function ($q) use ($request) {
                $q->whereNull('end_date')->where('shift_id', $request->shift_id);
            });
        }

        $employees = $employeeQuery->get();
        $batchId = 'BATCH-' . Carbon::now()->format('YmdHis');

        // Move all previously current payroll records to Previous Payroll page
        Payroll::where('is_current', true)->update(['is_current' => false]);

        foreach ($employees as $employee) {
            $employeeStartDate = Carbon::parse($startDate);
            $employeeEndDate = Carbon::parse($endDate);

            // Check if a payroll record already exists for this employee for this month/year cycle
            $existingPayroll = Payroll::where('employee_id', $employee->id)
                ->where('payroll_month', $month)
                ->where('payroll_year', $year)
                ->first();

            if ($existingPayroll) {
                // Update/replace the existing payroll record for this cycle
                $existingPayroll->update([
                    'batch_id' => $batchId,
                    'is_current' => true,
                    'currency' => $employee->salary()[0],
                    'base' => $employee->salary()[1],
                    'total_payable' => $employee->salary()[1],
                    'performance_multiplier' => 1,
                    'due_date' => $employeeEndDate->toDateString(),
                    'period_start' => $employeeStartDate->toDateString(),
                    'period_end' => $employeeEndDate->toDateString(),
                    'status' => false,
                    'is_reviewed' => false,
                ]);
                $payroll = $existingPayroll;

                // Reset additions & deductions for fresh calculation
                \App\Models\Addition::where('payroll_id', $payroll->id)->delete();
                \App\Models\Deduction::where('payroll_id', $payroll->id)->delete();
            } else {
                // Create a new payroll record if no record exists for this employee for this cycle
                $payroll = Payroll::create([
                    'employee_id' => $employee->id,
                    'batch_id' => $batchId,
                    'is_current' => true,
                    'currency' => $employee->salary()[0],
                    'base' => $employee->salary()[1],
                    'total_payable' => $employee->salary()[1],
                    'performance_multiplier' => 1,
                    'due_date' => $employeeEndDate->toDateString(),
                    'period_start' => $employeeStartDate->toDateString(),
                    'period_end' => $employeeEndDate->toDateString(),
                    'payroll_month' => $month,
                    'payroll_year' => $year,
                ]);
            }

            $activeSalary = $employee->salaries()->whereNull('end_date')->first();

            \App\Models\Addition::create([
                'payroll_id' => $payroll->id,
                "due_date" => $employeeEndDate->toDateString(),
                'custom_items' => $activeSalary ? $activeSalary->custom_additions : null,
            ]);

            \App\Models\Deduction::create([
                'payroll_id' => $payroll->id,
                "due_date" => $employeeEndDate->toDateString(),
                'custom_items' => $activeSalary ? $activeSalary->custom_deductions : null,
            ]);

            // Calculate exact snapshot numbers
            $this->payrollServices->recalculatePayroll($payroll->id);
        }
        
        return redirect()->back();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $res = $this->validationServices->validatePayrollReviewDetails($request);
        return $this->payrollServices->updatePayroll($res, $id);
    }

    public function updateAttendance(Request $request, string $id)
    {
        $request->validate([
            'attendance_id' => 'nullable|exists:attendances,id',
            'date' => 'nullable|date',
            'status' => 'required|in:on_time,late,absent,missed,early_departure,leave',
            'sign_in_time' => 'nullable|date_format:H:i',
            'sign_off_time' => 'nullable|date_format:H:i',
        ]);

        $payroll = Payroll::findOrFail($id);
        $status = $request->status;

        if ($request->filled('attendance_id')) {
            $attendance = \App\Models\Attendance::findOrFail($request->attendance_id);
            $attendance->update([
                'status' => $status,
                'sign_in_time' => $request->sign_in_time,
                'sign_off_time' => $request->sign_off_time,
            ]);
        } else if ($request->filled('date')) {
            \App\Models\Attendance::updateOrCreate(
                [
                    'employee_id' => $payroll->employee_id,
                    'date' => $request->date,
                ],
                [
                    'status' => $status,
                    'sign_in_time' => $request->sign_in_time,
                    'sign_off_time' => $request->sign_off_time,
                ]
            );
        }

        $this->payrollServices->recalculatePayroll($id);

        return redirect()->back();
    }

    /**
     * Update Status.
     */
    public function updateStatus(Request $request, string $id)
    {
        $this->payrollServices->updatePayrollStatus($request, $id);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if (!isAdmin()) abort(403);

        $payroll = Payroll::findOrFail($id);

        $payroll->additions()->delete();
        $payroll->deductions()->delete();
        $payroll->delete();

        return redirect()->route('payrolls.index');
    }

    /**
     * Download Payroll PDF Pay Slip.
     */
    public function pdf(string $id, Request $request)
    {
        $payroll = Payroll::with([
            'employee',
            'employee.employeeShifts.shift',
            'employee.employeePositions.position',
            'additions',
            'deductions'
        ])->findOrFail($id);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.salary-slip', compact('payroll'))->setPaper('a4', 'portrait');
        $fileName = 'PaySlip_' . str_replace(' ', '_', $payroll->employee->name) . '_' . Carbon::parse($payroll->period_start)->format('M_Y') . '.pdf';
        
        return $pdf->download($fileName);
    }

    /**
     * Export Payroll to CSV or PDF.
     */
    public function export(string $id, Request $request)
    {
        if ($request->query('format') === 'pdf') {
            return $this->pdf($id);
        }

        $payroll = Payroll::with('employee')->findOrFail($id);
        
        $fileName = 'Payroll_' . $payroll->employee->name . '_' . $payroll->period_start . '_to_' . $payroll->period_end . '.csv';
        
        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $columns = array('Payroll ID', 'Employee Name', 'Period Start', 'Period End', 'Base Salary', 'Total Payable', 'Status', 'Due Date');

        $callback = function() use($payroll, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            
            fputcsv($file, array(
                $payroll->id,
                $payroll->employee->name,
                $payroll->period_start,
                $payroll->period_end,
                $payroll->currency . ' ' . $payroll->base,
                $payroll->currency . ' ' . $payroll->total_payable,
                $payroll->status ? "Paid" : ($payroll->is_reviewed ? "Reviewed" : "Pending Review"),
                $payroll->due_date
            ));

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
