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

        // Status Filter
        if ($statusParam == 'pending') {
            $payrolls->where('status', false)->where('is_reviewed', false);
        } else if ($statusParam == 'reviewed') {
            $payrolls->where('status', false)->where('is_reviewed', true);
        } else if ($statusParam == 'paid') {
            $payrolls->where('status', true);
        }

        return Inertia::render('Payroll/Payrolls', [
            'payrolls' => $payrolls->paginate(config('constants.data.pagination_count')),
            "dateParam" => $date,
            "statusParam" => $statusParam,
            "employees" => \App\Models\Employee::select('id', 'name')->get(),
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

        if ($payroll->period_start && $payroll->period_end) {
            $startDate = Carbon::parse($payroll->period_start);
            $endDate = Carbon::parse($payroll->period_end);
            $totalDays = $startDate->diffInDays($endDate) + 1;
            $dates = [$startDate->year, $startDate->month, $startDate->day, $endDate->year, $endDate->month, $endDate->day];
            
            $weekendOffDays = [$payroll->employee->weekly_off_day];
            $offDays = $commonServices->calcOffDays($weekendOffDays, $payroll->employee->hired_on, $dates);
            $holidays = $commonServices->countHolidays($payroll->employee->hired_on, $dates);
            
            $month_stats = [
                'attendable_days' => $totalDays - $offDays - $holidays,
                'attended' => \App\Models\Attendance::where('employee_id', $payroll->employee->id)
                    ->whereBetween('date', [$payroll->period_start, $payroll->period_end])
                    ->where('status', 'on_time')
                    ->count(),
                'absented' => \App\Models\Attendance::where('employee_id', $payroll->employee->id)
                    ->whereBetween('date', [$payroll->period_start, $payroll->period_end])
                    ->whereIn('status', ['missed', 'absent'])
                    ->count(),
                'late' => \App\Models\Attendance::where('employee_id', $payroll->employee->id)
                    ->whereBetween('date', [$payroll->period_start, $payroll->period_end])
                    ->where('status', 'late')
                    ->count(),
            ];
            $hours = $payroll->employee->periodHours($payroll->period_start, $payroll->period_end);
        } else {
            $payrollDate = Carbon::parse($payroll->due_date)->subMonthNoOverflow();
            $dates = [$payrollDate->year, $payrollDate->month, 1, $payrollDate->year, $payrollDate->month, $payrollDate->daysInMonth];
            $month_stats = $commonServices->getMonthStats($payroll->employee, $dates);
            $hours = $payroll->employee->monthHours($payrollDate->year, $payrollDate->month);
        }

        // Calculate pending loans and advance payments for this payroll
        $activeLoans = \App\Models\Loan::where('employee_id', $payroll->employee->id)
            ->where('status', 'active')
            ->get();
        
        $loanDeduction = 0;
        foreach ($activeLoans as $loan) {
            $pct = ($loan->deduction_percentage && (float)$loan->deduction_percentage > 0) ? (float)$loan->deduction_percentage : 100;
            $deduction = (float)$loan->total_amount * ($pct / 100);
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

        $attendances = \App\Models\Attendance::where('employee_id', $payroll->employee->id)
            ->whereYear('date', $payroll->payroll_year ?? Carbon::parse($payroll->period_start)->year)
            ->whereMonth('date', $payroll->payroll_month ?? Carbon::parse($payroll->period_start)->month)
            ->get();

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
            'attendances' => $attendances,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'month_year.month' => 'required|integer',
            'month_year.year' => 'required|integer',
            'employee_id' => 'nullable|exists:employees,id',
        ]);

        $year = $request->month_year['year'];
        $month = $request->month_year['month'] + 1; // VueDatePicker month is 0-indexed

        $startDate = Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
        
        $employees = $request->employee_id ? \App\Models\Employee::where('id', $request->employee_id)->get() : \App\Models\Employee::all();

        foreach ($employees as $employee) {
            $employeeStartDate = Carbon::parse($startDate);
            $employeeEndDate = Carbon::parse($endDate);

            // Find overlapping payrolls for this employee
            $existingPayrolls = Payroll::where('employee_id', $employee->id)
                ->where('payroll_month', $month)
                ->where('payroll_year', $year)
                ->get();

            if ($existingPayrolls->isNotEmpty()) {
                continue; // Skip this employee if payroll for this month already exists
            }

            $payroll = Payroll::create([
                'employee_id' => $employee->id,
                'currency' => $employee->salary()[0],
                'base' => $employee->salary()[1],
                'total_payable' => $employee->salary()[1],
                'performance_multiplier' => 1,
                "due_date" => Carbon::now()->toDateString(),
                'period_start' => $employeeStartDate->toDateString(),
                'period_end' => $employeeEndDate->toDateString(),
                'payroll_month' => $month,
                'payroll_year' => $year,
            ]);

            $activeSalary = $employee->salaries()->whereNull('end_date')->first();

            \App\Models\Addition::create([
                'payroll_id' => $payroll->id,
                "due_date" => Carbon::now()->toDateString(),
                'custom_items' => $activeSalary ? $activeSalary->custom_additions : null,
            ]);

            \App\Models\Deduction::create([
                'payroll_id' => $payroll->id,
                "due_date" => Carbon::now()->toDateString(),
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
            'attendance_id' => 'required|exists:attendances,id',
            'status' => 'required|in:on_time,late,absent,missed,early_departure,leave', // Added leave for demo
            'sign_in_time' => 'nullable|date_format:H:i',
            'sign_off_time' => 'nullable|date_format:H:i',
        ]);

        $attendance = \App\Models\Attendance::findOrFail($request->attendance_id);
        $status = $request->status;
        
        if ($status === 'leave') {
            $leaveCount = \App\Models\Attendance::where('employee_id', $attendance->employee_id)
                ->whereMonth('date', \Carbon\Carbon::parse($attendance->date)->month)
                ->whereYear('date', \Carbon\Carbon::parse($attendance->date)->year)
                ->where('status', 'leave')
                ->where('id', '!=', $attendance->id)
                ->count();
                
            if ($leaveCount >= 4) {
                $status = 'absent';
            }
        }

        $attendance->update([
            'status' => $status,
            'sign_in_time' => $request->sign_in_time,
            'sign_off_time' => $request->sign_off_time,
        ]);

        $payroll = $this->payrollServices->recalculatePayroll($id);

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
