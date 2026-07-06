<?php

namespace App\Http\Controllers;

use App\Models\CashTransaction;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class CashTransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = CashTransaction::with('employee')->orderBy('date', 'desc')->orderBy('id', 'desc');

        if (!isAdmin()) {
            $query->where('employee_id', auth()->user()->id);
        }

        // Apply Filters
        if ($request->filled('employee_id') && isAdmin()) {
            $query->where('employee_id', $request->employee_id);
        }
        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('start_date')) {
            $query->where('date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('date', '<=', $request->end_date);
        }

        $transactions = $query->paginate(config('constants.data.pagination_count'))->withQueryString();

        // Calculate Totals based on filtered date and type
        $totalCashIn = (clone $query)->where('transaction_type', 'cash_in')->sum('amount');
        $totalCashOut = (clone $query)->where('transaction_type', 'cash_out')->sum('amount');
        
        // Balance calculation (All-time absolute balance for the employee if an employee is selected/user is not admin)
        $currentBalance = 0;
        if (!isAdmin()) {
            $empId = auth()->user()->id;
            $allCashIn = CashTransaction::where('employee_id', $empId)->where('transaction_type', 'cash_in')->sum('amount');
            $allCashOut = CashTransaction::where('employee_id', $empId)->where('transaction_type', 'cash_out')->sum('amount');
            $currentBalance = $allCashIn - $allCashOut;
        } elseif ($request->filled('employee_id')) {
            $empId = $request->employee_id;
            $allCashIn = CashTransaction::where('employee_id', $empId)->where('transaction_type', 'cash_in')->sum('amount');
            $allCashOut = CashTransaction::where('employee_id', $empId)->where('transaction_type', 'cash_out')->sum('amount');
            $currentBalance = $allCashIn - $allCashOut;
        } else {
            // For all employees, just show the difference of the filtered view
            $currentBalance = $totalCashIn - $totalCashOut;
        }

        $employees = Employee::select('id', 'name')->get();

        return Inertia::render('CashMaintenance/CashTransactions', [
            'cashTransactions' => $transactions,
            'employees' => $employees,
            'filters' => $request->only(['employee_id', 'transaction_type', 'status', 'start_date', 'end_date']),
            'totals' => [
                'total_cash_in' => $totalCashIn,
                'total_cash_out' => $totalCashOut,
                'current_balance' => $currentBalance,
            ]
        ]);
    }

    public function store(Request $request)
    {
        if (!isAdmin()) abort(403);

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'transaction_type' => 'required|in:cash_in,cash_out',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'description' => 'nullable|string|max:1000',
            'reference' => 'nullable|string|max:255',
            'status' => 'required|in:pending,approved,rejected,completed',
        ]);

        CashTransaction::create([
            'employee_id' => $request->employee_id,
            'transaction_type' => $request->transaction_type,
            'amount' => $request->amount,
            'date' => $request->date,
            'description' => $request->description,
            'reference' => $request->reference,
            'status' => $request->status,
        ]);

        return redirect()->back();
    }

    public function destroy(string $id)
    {
        if (!isAdmin()) abort(403);
        CashTransaction::findOrFail($id)->delete();
        return redirect()->back();
    }
}
