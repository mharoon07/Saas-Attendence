<?php

namespace App\Http\Controllers;

use App\Models\AdvancePayment;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdvancePaymentController extends Controller
{
    public function index(Request $request)
    {
        $paymentsQuery = AdvancePayment::with('employee')->orderBy('id', 'desc');
        
        if (!isAdmin()) {
            $paymentsQuery->where('employee_id', auth()->user()->id);
        }

        $payments = $paymentsQuery->paginate(config('constants.data.pagination_count'));
        $employees = Employee::select('id', 'name')->get();

        return Inertia::render('AdvancePayments/AdvancePayments', [
            'advancePayments' => $payments,
            'employees' => $employees,
        ]);
    }

    public function store(Request $request)
    {
        if (!isAdmin()) abort(403);

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'advance_amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'status' => 'required|in:pending,approved,rejected,completed',
        ]);

        AdvancePayment::create([
            'employee_id' => $request->employee_id,
            'advance_amount' => $request->advance_amount,
            'remaining_amount' => $request->advance_amount,
            'date' => $request->date,
            'status' => $request->status,
        ]);

        return redirect()->back();
    }

    public function update(Request $request, string $id)
    {
        if (!isAdmin()) abort(403);

        $payment = AdvancePayment::findOrFail($id);
        // Optional update method
    }

    public function destroy(string $id)
    {
        if (!isAdmin()) abort(403);
        AdvancePayment::findOrFail($id)->delete();
        return redirect()->back();
    }
}
