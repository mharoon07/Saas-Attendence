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
        $employees = Employee::select('id', 'name', 'device_employee_id')->get();

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
            'date' => \Carbon\Carbon::parse($request->date)->format('Y-m-d'),
            'status' => $request->status,
        ]);

        return redirect()->back();
    }

    public function update(Request $request, string $id)
    {
        if (!isAdmin()) abort(403);

        $payment = AdvancePayment::findOrFail($id);

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'advance_amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'status' => 'required|in:pending,approved,rejected,completed',
        ]);

        $deductedAmount = (float)$payment->deducted_amount;
        $advanceAmount = (float)$request->advance_amount;
        $remainingAmount = max(0, $advanceAmount - $deductedAmount);
        $status = ($remainingAmount <= 0) ? 'completed' : $request->status;

        $payment->update([
            'employee_id' => $request->employee_id,
            'advance_amount' => $advanceAmount,
            'remaining_amount' => $remainingAmount,
            'date' => \Carbon\Carbon::parse($request->date)->format('Y-m-d'),
            'status' => $status,
        ]);

        return redirect()->back();
    }

    public function destroy(string $id)
    {
        if (!isAdmin()) abort(403);
        AdvancePayment::findOrFail($id)->delete();
        return redirect()->back();
    }
}
