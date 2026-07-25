<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LoanController extends Controller
{
    public function index(Request $request)
    {
        $loansQuery = Loan::with('employee')->orderBy('id', 'desc');
        
        if (!isAdmin()) {
            $loansQuery->where('employee_id', auth()->user()->id);
        }

        $loans = $loansQuery->paginate(config('constants.data.pagination_count'));
        $employees = Employee::select('id', 'name', 'device_employee_id')->get();

        return Inertia::render('Loans/Loans', [
            'loans' => $loans,
            'employees' => $employees,
        ]);
    }

    public function store(Request $request)
    {
        if (!isAdmin()) abort(403);

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'loan_amount' => 'required|numeric|min:0',
            'deduction_percentage' => 'required|numeric|min:0|max:100',
            'date' => 'required|date',
            'status' => 'required|in:active,completed',
        ]);

        Loan::create([
            'employee_id' => $request->employee_id,
            'total_amount' => $request->loan_amount,
            'deduction_percentage' => $request->deduction_percentage,
            'remaining_balance' => $request->loan_amount,
            'date' => \Carbon\Carbon::parse($request->date)->format('Y-m-d'),
            'status' => $request->status,
        ]);

        return redirect()->back();
    }

    public function update(Request $request, string $id)
    {
        if (!isAdmin()) abort(403);

        $loan = Loan::findOrFail($id);

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'loan_amount' => 'required|numeric|min:0',
            'deduction_percentage' => 'required|numeric|min:0|max:100',
            'date' => 'required|date',
            'status' => 'required|in:active,completed',
        ]);

        $paidAmount = (float)$loan->paid_amount;
        $totalAmount = (float)$request->loan_amount;
        $remainingBalance = max(0, $totalAmount - $paidAmount);
        $status = ($remainingBalance <= 0) ? 'completed' : $request->status;

        $loan->update([
            'employee_id' => $request->employee_id,
            'total_amount' => $totalAmount,
            'deduction_percentage' => $request->deduction_percentage,
            'remaining_balance' => $remainingBalance,
            'date' => \Carbon\Carbon::parse($request->date)->format('Y-m-d'),
            'status' => $status,
        ]);

        return redirect()->back();
    }

    public function destroy(string $id)
    {
        if (!isAdmin()) abort(403);
        Loan::findOrFail($id)->delete();
        return redirect()->back();
    }
}
