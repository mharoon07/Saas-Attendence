<?php

namespace App\Services;


use App\Mail\PayrollEmail;
use App\Models\Globals;
use App\Models\Payroll;
use Arr;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class PayrollServices
{

    private function quickPay($payroll, $res)
    {
        $payroll->additions->update([
            'status' => true,
        ]);
        $payroll->deductions->update([
            'status' => true,
        ]);
        $payroll->update([
            'performance_multiplier' => 1,
            'total_deductions' => 0,
            'total_additions' => 0,
            'total_payable' => $payroll->base,
            'is_reviewed' => true,
            'status' => true, // paid
        ]);
        if ($res['quick_pay_send_email']) {
            Mail::to($payroll->employee->email)->queue(new PayrollEmail($payroll));
        }
        return to_route('payrolls.show', ['payroll' => $payroll]);
    }
    public function recalculatePayroll($id, $res = null)
    {
        $payroll = Payroll::with(['employee', 'additions', 'deductions'])->findOrFail($id);
        $employee = $payroll->employee;

        if (!$payroll->additions) {
            $payroll->additions()->create([
                'due_date' => $payroll->due_date,
            ]);
            $payroll->load('additions');
        }

        if (!$payroll->deductions) {
            $payroll->deductions()->create([
                'due_date' => $payroll->due_date,
            ]);
            $payroll->load('deductions');
        }

        $year = $payroll->payroll_year;
        $month = $payroll->payroll_month;

        if (!$year || !$month) {
            // Fallback for older payrolls without month/year
            $startDate = Carbon::parse($payroll->period_start);
            $year = $startDate->year;
            $month = $startDate->month;
        }

        $monthEnd = Carbon::createFromDate($year, $month, 1)->endOfMonth()->format('j');
        $monthDates = [$year, $month, 1, $year, $month, $monthEnd];

        $commonServices = new \App\Services\CommonServices();
        $weekendOffDays = [$employee->weekly_off_day];

        $holidaysCount = $commonServices->countHolidays($employee->hired_on, $monthDates);
        $weekendsCount = $commonServices->calcOffDays($weekendOffDays, $employee->hired_on, $monthDates);
        
        $attendableDays = $monthEnd - $holidaysCount - $weekendsCount;

        $attended = \App\Models\Attendance::where('employee_id', $employee->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->whereNotIn('status', ['missed', 'absent'])
            ->count();
            
        $absented = \App\Models\Attendance::where('employee_id', $employee->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->whereIn('status', ['missed', 'absent'])
            ->count();
            
        $dailySalary = $attendableDays > 0 ? $payroll->base / $attendableDays : 0;

        $approvedLeaves = \App\Models\Leave::where('employee_id', $employee->id)
            ->where('status', 'Approved')
            ->where(function($q) use ($payroll) {
                $q->whereBetween('start_date', [$payroll->period_start, $payroll->period_end])
                  ->orWhereBetween('end_date', [$payroll->period_start, $payroll->period_end])
                  ->orWhere(function($sub) use ($payroll) {
                      $sub->where('start_date', '<=', $payroll->period_start)
                          ->where('end_date', '>=', $payroll->period_end);
                  });
            })
            ->get();

        $leaveDays = 0;
        $unpaidLeaveDays = 0;

        foreach ($approvedLeaves as $leave) {
            $start = Carbon::parse(max($leave->start_date, $payroll->period_start));
            $end = Carbon::parse(min($leave->end_date, $payroll->period_end));

            if ($leave->half_day) {
                $days = 0.5;
            } else {
                $days = $start->diffInDays($end) + 1;
            }

            $leaveDays += $days;

            if (strtolower($leave->leave_type) === 'unpaid') {
                $unpaidLeaveDays += $days;
            }
        }

        $unpaidLeaveDeduction = round($unpaidLeaveDays * $dailySalary, 2);

        $hours = $employee->monthHours($year, $month);

        $extraHourRate = $res['extra_hour_rate'] ?? $payroll->additions->extra_hour_rate ?? 0;
        $negativeHourRate = $res['negative_hour_rate'] ?? $payroll->deductions->negative_hour_rate ?? 0;

        $overtimeHours = $hours['hoursDifference'] > 0 ? $hours['hoursDifference'] : 0;
        $overtime = $overtimeHours * $extraHourRate;
        
        $undertimeHours = $hours['hoursDifference'] < 0 ? $hours['hoursDifference'] * -1 : 0;
        $undertime = $undertimeHours * $negativeHourRate;

        $globals = Globals::first();
        $income_tax = (($globals ? $globals->income_tax : 14.0) / 100) * $payroll->base;

        // Loans & Advances
        $loanDeduction = 0;
        if (isset($res['loan_deduction']) && (float)$res['loan_deduction'] > 0) {
            $loanDeduction = (float)$res['loan_deduction'];
        } else {
            $activeLoans = \App\Models\Loan::where('employee_id', $employee->id)->where('status', 'active')->get();
            foreach ($activeLoans as $loan) {
                $pct = ($loan->deduction_percentage && (float)$loan->deduction_percentage > 0) ? (float)$loan->deduction_percentage : 100;
                $deduction = (float)$loan->total_amount * ($pct / 100);
                if ($deduction <= 0) {
                    $deduction = (float)$loan->remaining_balance;
                }
                $loanDeduction += min($deduction, (float)$loan->remaining_balance);
            }
        }

        $advancePaymentDeduction = 0;
        if (isset($res['advance_payment_deduction']) && (float)$res['advance_payment_deduction'] > 0) {
            $advancePaymentDeduction = (float)$res['advance_payment_deduction'];
        } else {
            $activeAdvances = \App\Models\AdvancePayment::where('employee_id', $employee->id)
                ->whereIn('status', ['approved', 'pending'])
                ->where('remaining_amount', '>', 0)
                ->get();
            $advancePaymentDeduction = (float)$activeAdvances->sum('remaining_amount');
        }

        $payroll->additions->update([
            'custom_items' => $res['custom_additions'] ?? $payroll->additions->custom_items ?? [],
            'overtime' => $overtime,
            'extra_hour_rate' => $extraHourRate,
            'status' => true,
        ]);

        $payroll->deductions->update([
            'income_tax' => $income_tax,
            'custom_items' => $res['custom_deductions'] ?? $payroll->deductions->custom_items ?? [],
            'undertime' => $undertime,
            'negative_hour_rate' => $negativeHourRate,
            'unpaid_leave_deduction' => $unpaidLeaveDeduction,
            'loan_deduction' => $loanDeduction,
            'advance_payment_deduction' => $advancePaymentDeduction,
            'status' => true,
        ]);

        if (isset($res['metrics'])) {
            for ($i = 0; $i < count($payroll->evaluations); $i++) {
                $ev = $payroll->evaluations[$i];
                $grade = $ev->metric->findGrade($res['metrics'][$i]);
                $payroll->evaluations[$i]->where([
                    'metric_id' => $res['metricsIDs'][$i],
                    'payroll_id' => $payroll->id,
                    'employee_id' => $payroll->employee_id,
                ])->update([
                    // [GRADE - WEIGHT - STEP - WEIGHTED POINT]
                    'score' => [$grade, $ev->metric->weight, $ev->metric->step,
                        round(1 + (abs(1 - $res['metrics'][$i]) * $ev->metric->weight * ($res['metrics'][$i] > 1 ? 1 : -1)), 2)],
                ]);
            }
        }

        $multiplier = $res['performance_multiplier'] ?? $payroll->performance_multiplier ?? 1;

        $monthlySalary = $payroll->base;
        $dailySalary = $attendableDays > 0 ? $monthlySalary / $attendableDays : 0;
        $grossSalary = ($monthlySalary * $multiplier) + $payroll->additions->getSum();
        $netSalary = $grossSalary - $payroll->deductions->getSum();

        $payroll->update([
            'monthly_salary' => $monthlySalary,
            'daily_salary' => $dailySalary,
            'regular_working_days' => $attendableDays,
            'absent_days' => $absented,
            'leave_days' => $leaveDays,
            'overtime_hours' => $overtimeHours,
            'overtime_amount' => $overtime,
            'performance_multiplier' => $multiplier,
            'total_deductions' => $payroll->deductions->getSum(),
            'total_additions' => $payroll->additions->getSum(),
            'total_payable' => $netSalary,
            'gross_salary' => $grossSalary,
            'net_salary' => $netSalary,
            'is_reviewed' => isset($res) ? true : $payroll->is_reviewed,
        ]);

        return $payroll;
    }

    public function updatePayroll($res, $id)
    {
        $payroll = Payroll::findOrFail($id);

        if ($res['quick_pay']) {
            return $this->quickPay($payroll, $res);
        }
        
        $payroll = $this->recalculatePayroll($id, $res);

        return to_route('payrolls.show', ['payroll' => $payroll]);
    }

    public function updatePayrollStatus($request, $id){
        $request->validate([
            'status' => 'required|boolean',
            'sendEmail' => 'nullable|integer|in:0,1'
        ]);
        $payroll = Payroll::findOrFail($id);
        if (!$payroll->is_reviewed) {
            return response()->json(['Error' => 'Payroll must be reviewed before it can be paid.']);
        }
        $payroll->update([
            'status' => $request->status,
        ]);

        if ($request->status) {
            $this->applyDeductionsToBalances($payroll);
        }

        if ($request->sendEmail && $payroll->status) {
            Mail::to($payroll->employee->email)->queue(new PayrollEmail($payroll));
        }
    }

    private function applyDeductionsToBalances(Payroll $payroll)
    {
        $loanDeduction = $payroll->deductions->loan_deduction ?? 0;
        $advanceDeduction = $payroll->deductions->advance_payment_deduction ?? 0;

        if ($loanDeduction > 0) {
            $activeLoans = \App\Models\Loan::where('employee_id', $payroll->employee_id)
                ->where('status', 'active')
                ->get();
            
            $remainingDeduction = $loanDeduction;

            foreach ($activeLoans as $loan) {
                if ($remainingDeduction <= 0) break;

                $amountToDeduct = min($remainingDeduction, $loan->remaining_balance);
                
                $loan->paid_amount += $amountToDeduct;
                $loan->remaining_balance -= $amountToDeduct;
                
                if ($loan->remaining_balance <= 0) {
                    $loan->status = 'completed';
                }
                
                $loan->save();
                $remainingDeduction -= $amountToDeduct;
            }
        }

        if ($advanceDeduction > 0) {
            $activeAdvances = \App\Models\AdvancePayment::where('employee_id', $payroll->employee_id)
                ->whereIn('status', ['approved', 'pending'])
                ->where('remaining_amount', '>', 0)
                ->get();
            
            $remainingDeduction = $advanceDeduction;

            foreach ($activeAdvances as $advance) {
                if ($remainingDeduction <= 0) break;

                $amountToDeduct = min($remainingDeduction, $advance->remaining_amount);
                
                $advance->deducted_amount += $amountToDeduct;
                $advance->remaining_amount -= $amountToDeduct;
                
                if ($advance->remaining_amount <= 0) {
                    $advance->status = 'completed';
                }
                
                $advance->save();
                $remainingDeduction -= $amountToDeduct;
            }
        }
    }
}
