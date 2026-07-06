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
        $employees = Employee::select('id', 'name')->get();

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
            'date' => $request->date,
            'status' => $request->status,
        ]);

        return redirect()->back();
    }

    public function update(Request $request, string $id)
    {
        if (!isAdmin()) abort(403);

        $loan = Loan::findOrFail($id);
        // Add specific validation if needed
    }

    public function destroy(string $id)
    {
        if (!isAdmin()) abort(403);
        Loan::findOrFail($id)->delete();
        return redirect()->back();
    }
}
