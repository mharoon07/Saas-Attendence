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
            ->select('payrolls.id', 'due_date', 'currency', 'total_payable', 'employees.name as employee_name', 'status', 'is_reviewed')
            ->orderBy('id');

        // Limit to logged-in employee if not admin
        if (!isAdmin())
            $payrolls->where('payrolls.employee_id', auth()->user()->id);

        // Date Filter
        if ($date)
            $payrolls->whereYear('due_date', $request->date['year'])->whereMonth('due_date', $request->date['month'] + 1);

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
        $payroll = Payroll::with('employee')->findOrFail($id);
        $commonServices = new CommonServices();

        if ($payroll->period_start && $payroll->period_end) {
            $startDate = Carbon::parse($payroll->period_start);
            $endDate = Carbon::parse($payroll->period_end);
            $totalDays = $startDate->diffInDays($endDate) + 1;
            $dates = [$startDate->year, $startDate->month, $startDate->day, $endDate->year, $endDate->month, $endDate->day];
            
            $globalSettings = Globals::first();
            $weekendOffDays = $globalSettings ? json_decode($globalSettings->weekend_off_days) : ['friday', 'saturday'];
            if (!is_array($weekendOffDays)) {
                $weekendOffDays = ['friday', 'saturday'];
            }
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
                    ->where('status', 'missed')
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

        return Inertia::render('Payroll/PayrollReview', [
            'payroll' => $payroll,
            "month_stats" => $month_stats,
            'additions' => $payroll->additions, // PLACEHOLDER CODE. MUCH MORE WORK NEEDED HERE
            'deductions' => $payroll->deductions, // PLACEHOLDER CODE. MUCH MORE WORK NEEDED HERE
            'income_tax' => Globals::select('income_tax')->get()->first(), // PLACEHOLDER CODE. MUCH MORE WORK NEEDED HERE
            'shift_modifier' => $payroll->employee->activeShift()?->shift_payment_multiplier ?? 1,
            'hours' => $hours,
            'metrics' => Metric::where('created_at', '<=', $payroll->created_at)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'employee_id' => 'nullable|exists:employees,id',
        ]);

        $startDate = Carbon::parse($request->period_start)->toDateString();
        $endDate = Carbon::parse($request->period_end)->toDateString();
        
        $employees = $request->employee_id ? \App\Models\Employee::where('id', $request->employee_id)->get() : \App\Models\Employee::all();

        foreach ($employees as $employee) {
            $employeeStartDate = Carbon::parse($startDate);
            $employeeEndDate = Carbon::parse($endDate);

            // Find overlapping payrolls for this employee
            $existingPayrolls = Payroll::where('employee_id', $employee->id)
                ->where('period_start', '<=', $employeeEndDate->toDateString())
                ->where('period_end', '>=', $employeeStartDate->toDateString())
                ->orderBy('period_end', 'desc')
                ->get();

            // If there's an overlap, adjust the start date to the day after the latest period_end
            if ($existingPayrolls->isNotEmpty()) {
                $latestPeriodEnd = Carbon::parse($existingPayrolls->first()->period_end);
                
                // If the entire requested period is already covered, skip generating
                if ($latestPeriodEnd->greaterThanOrEqualTo($employeeEndDate)) {
                    continue; // Skip this employee
                }
                
                // Adjust start date
                $employeeStartDate = $latestPeriodEnd->addDay();
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
            ]);

            \App\Models\Addition::create([
                'payroll_id' => $payroll->id,
                "due_date" => Carbon::now()->toDateString(),
            ]);

            \App\Models\Deduction::create([
                'payroll_id' => $payroll->id,
                "due_date" => Carbon::now()->toDateString(),
            ]);
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
        Payroll::findOrFail($id)->delete();
    }

    /**
     * Export Payroll to CSV.
     */
    public function export(string $id)
    {
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
