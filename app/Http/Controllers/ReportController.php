<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\Employee;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function payroll(Request $request)
    {
        $format = $request->query('format', 'csv');
        $month = (int)$request->query('month', date('n'));
        $year = (int)$request->query('year', date('Y'));
        $employeeId = $request->query('employee_id', 'all');

        $query = Payroll::with(['employee', 'additions', 'deductions'])
            ->whereYear('due_date', $year)
            ->whereMonth('due_date', $month);

        if ($employeeId !== 'all') {
            $query->where('employee_id', $employeeId);
        }

        $fileName = "payroll_report_{$year}_{$month}.csv";

        // If PDF requested, render a simple HTML view and convert to PDF if possible.
        if (strtolower($format) === 'pdf') {
            $data = $query->get();
            $html = view('reports.pdf_payroll', compact('data', 'month', 'year'))->render();

            if (class_exists(\Dompdf\Dompdf::class)) {
                $dompdf = new \Dompdf\Dompdf();
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'landscape');
                $dompdf->render();
                return response($dompdf->output(), 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => "attachment; filename=payroll_report_{$year}_{$month}.pdf",
                ]);
            }

            // Fallback: return HTML so user can print to PDF from browser.
            return response($html, 200, [
                'Content-Type' => 'text/html',
                'Content-Disposition' => "attachment; filename=payroll_report_{$year}_{$month}.html",
            ]);
        }

        $callback = function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Employee ID', 'Employee Name', 'Base', 'Performance Multiplier',
                'Rewards', 'Incentives', 'Reimbursements', 'Shift Differentials', 'Overtime', 'Commissions',
                'Income Tax', 'Social Security', 'Health Insurance', 'Retirement Plan', 'Benefits', 'Union Fees', 'Undertime',
                'Total Additions', 'Total Deductions', 'Total Payable', 'Due Date', 'Status'
            ]);

            foreach ($query->get() as $p) {
                $adds = $p->additions ?? null;
                $deds = $p->deductions ?? null;

                fputcsv($handle, [
                    $p->employee_id,
                    optional($p->employee)->name,
                    $p->base,
                    $p->performance_multiplier,
                    optional($adds)->rewards ?? 0,
                    optional($adds)->incentives ?? 0,
                    optional($adds)->reimbursements ?? 0,
                    optional($adds)->shift_differentials ?? 0,
                    optional($adds)->overtime ?? 0,
                    optional($adds)->commissions ?? 0,
                    optional($deds)->income_tax ?? 0,
                    optional($deds)->social_security_contributions ?? 0,
                    optional($deds)->health_insurance ?? 0,
                    optional($deds)->retirement_plan ?? 0,
                    optional($deds)->benefits ?? 0,
                    optional($deds)->union_fees ?? 0,
                    optional($deds)->undertime ?? 0,
                    $p->total_additions,
                    $p->total_deductions,
                    $p->total_payable,
                    $p->due_date,
                    $p->status ? 'Paid' : ($p->is_reviewed ? 'Reviewed' : 'Pending'),
                ]);
            }

            fclose($handle);
        };

        return response()->streamDownload($callback, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function attendance(Request $request)
    {
        $format = $request->query('format', 'csv');
        $month = (int)$request->query('month', date('n'));
        $year = (int)$request->query('year', date('Y'));
        $employeeId = $request->query('employee_id', 'all');

        $fileName = "attendance_report_{$year}_{$month}.csv";

        if (strtolower($format) === 'pdf') {
            $employees = $employeeId === 'all' ? Employee::all() : Employee::where('id', $employeeId)->get();
            $rows = [];
            foreach ($employees as $emp) {
                $attended = $emp->attendances()->whereYear('date', $year)->whereMonth('date', $month)->whereNotIn('status', ['missed', 'absent'])->count();
                try {
                    $late = $emp->attendances()->whereYear('date', $year)->whereMonth('date', $month)->where('status', 'late')->count();
                } catch (\Exception $e) {
                    $late = $emp->attendances()->whereYear('date', $year)->whereMonth('date', $month)->where('is_late', true)->count();
                }
                $hours = $emp->monthHours($year, $month);
                $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                $absent = max(0, $daysInMonth - $attended);
                $rows[] = [
                    'id' => $emp->id,
                    'name' => $emp->name,
                    'attended' => $attended,
                    'absent' => $absent,
                    'late' => $late,
                    'hours' => $hours['actualHours'] ?? 0,
                ];
            }
            $html = view('reports.pdf_attendance', compact('rows', 'month', 'year'))->render();
            if (class_exists(\Dompdf\Dompdf::class)) {
                $dompdf = new \Dompdf\Dompdf();
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'landscape');
                $dompdf->render();
                return response($dompdf->output(), 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => "attachment; filename=attendance_report_{$year}_{$month}.pdf",
                ]);
            }
            return response($html, 200, [
                'Content-Type' => 'text/html',
                'Content-Disposition' => "attachment; filename=attendance_report_{$year}_{$month}.html",
            ]);
        }

        $callback = function () use ($employeeId, $month, $year) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Employee ID', 'Employee Name', 'Days Present', 'Days Absent', 'Late Count', 'Total Hours']);

            $employees = $employeeId === 'all' ? Employee::all() : Employee::where('id', $employeeId)->get();

            foreach ($employees as $emp) {
                $attended = $emp->attendances()->whereYear('date', $year)->whereMonth('date', $month)->whereNotIn('status', ['missed', 'absent'])->count();
                try {
                    $late = $emp->attendances()->whereYear('date', $year)->whereMonth('date', $month)->where('status', 'late')->count();
                } catch (\Exception $e) {
                    $late = $emp->attendances()->whereYear('date', $year)->whereMonth('date', $month)->where('is_late', true)->count();
                }

                $hours = $emp->monthHours($year, $month);

                $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                $absent = max(0, $daysInMonth - $attended);

                fputcsv($handle, [
                    $emp->id,
                    $emp->name,
                    $attended,
                    $absent,
                    $late,
                    $hours['actualHours'] ?? 0,
                ]);
            }

            fclose($handle);
        };

        return response()->streamDownload($callback, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function late(Request $request)
    {
        $format = $request->query('format', 'csv');
        $month = (int)$request->query('month', date('n'));
        $year = (int)$request->query('year', date('Y'));
        $employeeId = $request->query('employee_id', 'all');

        $fileName = "late_report_{$year}_{$month}.csv";

        if (strtolower($format) === 'pdf') {
            $query = Attendance::with('employee')
                ->whereYear('date', $year)
                ->whereMonth('date', $month)
                ->where('status', 'late')
                ->orderBy('date');
            if ($employeeId !== 'all') {
                $query->where('employee_id', $employeeId);
            }
            $rows = $query->get();
            $html = view('reports.pdf_late', compact('rows', 'month', 'year'))->render();
            if (class_exists(\Dompdf\Dompdf::class)) {
                $dompdf = new \Dompdf\Dompdf();
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'landscape');
                $dompdf->render();
                return response($dompdf->output(), 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => "attachment; filename=late_report_{$year}_{$month}.pdf",
                ]);
            }
            return response($html, 200, [
                'Content-Type' => 'text/html',
                'Content-Disposition' => "attachment; filename=late_report_{$year}_{$month}.html",
            ]);
        }

        $callback = function () use ($employeeId, $month, $year) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Employee ID', 'Employee Name', 'Date', 'Status', 'Sign In', 'Sign Off', 'Notes']);

            $query = Attendance::with('employee')
                ->whereYear('date', $year)
                ->whereMonth('date', $month)
                ->where('status', 'late')
                ->orderBy('date');

            if ($employeeId !== 'all') {
                $query->where('employee_id', $employeeId);
            }

            foreach ($query->get() as $a) {
                fputcsv($handle, [
                    $a->employee_id,
                    optional($a->employee)->name,
                    $a->date,
                    $a->status,
                    $a->sign_in_time,
                    $a->sign_off_time,
                    $a->notes,
                ]);
            }

            fclose($handle);
        };

        return response()->streamDownload($callback, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function loans(Request $request)
    {
        $format = $request->query('format', 'csv');
        $month = (int)$request->query('month', date('n'));
        $year = (int)$request->query('year', date('Y'));
        $employeeId = $request->query('employee_id', 'all');

        $query = \App\Models\Loan::with('employee')
            ->whereYear('date', $year)
            ->whereMonth('date', $month);

        if ($employeeId !== 'all') {
            $query->where('employee_id', $employeeId);
        }

        if (strtolower($format) === 'pdf') {
            $rows = $query->get();
            try {
                $html = view('reports.pdf_loans', compact('rows', 'month', 'year'))->render();
                if (class_exists(\Dompdf\Dompdf::class)) {
                    $dompdf = new \Dompdf\Dompdf();
                    $dompdf->loadHtml($html);
                    $dompdf->setPaper('A4', 'landscape');
                    $dompdf->render();
                    return response($dompdf->output(), 200, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => "attachment; filename=loans_report_{$year}_{$month}.pdf",
                    ]);
                }
                return response($html, 200, [
                    'Content-Type' => 'text/html',
                    'Content-Disposition' => "attachment; filename=loans_report_{$year}_{$month}.html",
                ]);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Failed to generate PDF report: ' . $e->getMessage()], 500);
            }
        }

        $fileName = "loans_report_{$year}_{$month}.csv";

        $callback = function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Loan ID', 'Employee Name', 'Total Amount', 'Deduction %', 'Paid Amount', 'Remaining Balance', 'Date', 'Status']);

            foreach ($query->get() as $loan) {
                fputcsv($handle, [
                    $loan->id,
                    optional($loan->employee)->name,
                    $loan->total_amount,
                    $loan->deduction_percentage . '%',
                    $loan->paid_amount,
                    $loan->remaining_balance,
                    $loan->date,
                    $loan->status,
                ]);
            }

            fclose($handle);
        };

        return response()->streamDownload($callback, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function cashTransactions(Request $request)
    {
        $format = $request->query('format', 'csv');
        $month = (int)$request->query('month', date('n'));
        $year = (int)$request->query('year', date('Y'));
        $employeeId = $request->query('employee_id', 'all');

        $query = \App\Models\CashTransaction::with('employee')
            ->whereYear('date', $year)
            ->whereMonth('date', $month);

        if ($employeeId !== 'all') {
            $query->where('employee_id', $employeeId);
        }

        if (strtolower($format) === 'pdf') {
            $rows = $query->get();
            try {
                $html = view('reports.pdf_cash_transactions', compact('rows', 'month', 'year'))->render();
                if (class_exists(\Dompdf\Dompdf::class)) {
                    $dompdf = new \Dompdf\Dompdf();
                    $dompdf->loadHtml($html);
                    $dompdf->setPaper('A4', 'landscape');
                    $dompdf->render();
                    return response($dompdf->output(), 200, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => "attachment; filename=cash_transactions_report_{$year}_{$month}.pdf",
                    ]);
                }
                return response($html, 200, [
                    'Content-Type' => 'text/html',
                    'Content-Disposition' => "attachment; filename=cash_transactions_report_{$year}_{$month}.html",
                ]);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Failed to generate PDF report: ' . $e->getMessage()], 500);
            }
        }

        $fileName = "cash_transactions_report_{$year}_{$month}.csv";

        $callback = function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Transaction ID', 'Employee Name', 'Type', 'Amount', 'Date', 'Description', 'Reference', 'Status']);

            foreach ($query->get() as $tx) {
                fputcsv($handle, [
                    $tx->id,
                    optional($tx->employee)->name,
                    $tx->transaction_type,
                    $tx->amount,
                    $tx->date,
                    $tx->description,
                    $tx->reference,
                    $tx->status,
                ]);
            }

            fclose($handle);
        };

        return response()->streamDownload($callback, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function advancePayments(Request $request)
    {
        $format = $request->query('format', 'csv');
        $month = (int)$request->query('month', date('n'));
        $year = (int)$request->query('year', date('Y'));
        $employeeId = $request->query('employee_id', 'all');

        $query = \App\Models\AdvancePayment::with('employee')
            ->whereYear('date', $year)
            ->whereMonth('date', $month);

        if ($employeeId !== 'all') {
            $query->where('employee_id', $employeeId);
        }

        if (strtolower($format) === 'pdf') {
            $rows = $query->get();
            try {
                $html = view('reports.pdf_advance_payments', compact('rows', 'month', 'year'))->render();
                if (class_exists(\Dompdf\Dompdf::class)) {
                    $dompdf = new \Dompdf\Dompdf();
                    $dompdf->loadHtml($html);
                    $dompdf->setPaper('A4', 'landscape');
                    $dompdf->render();
                    return response($dompdf->output(), 200, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => "attachment; filename=advance_payments_report_{$year}_{$month}.pdf",
                    ]);
                }
                return response($html, 200, [
                    'Content-Type' => 'text/html',
                    'Content-Disposition' => "attachment; filename=advance_payments_report_{$year}_{$month}.html",
                ]);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Failed to generate PDF report: ' . $e->getMessage()], 500);
            }
        }

        $fileName = "advance_payments_report_{$year}_{$month}.csv";

        $callback = function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Advance ID', 'Employee Name', 'Advance Amount', 'Remaining Amount', 'Date', 'Status']);

            foreach ($query->get() as $adv) {
                fputcsv($handle, [
                    $adv->id,
                    optional($adv->employee)->name,
                    $adv->advance_amount,
                    $adv->remaining_amount,
                    $adv->date,
                    $adv->status,
                ]);
            }

            fclose($handle);
        };

        return response()->streamDownload($callback, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
