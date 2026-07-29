<?php

namespace App\Http\Controllers;

use App\Models\Leave;
use App\Models\Employee;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class LeaveController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Leave::with('employee');

        // Check if employee is logged in (non-admin)
        if (!isAdmin()) {
            $query->where('employee_id', auth()->user()->id);
        } else {
            // Admin can filter by employee
            if ($request->filled('employee_id') && $request->employee_id !== 'all') {
                $query->where('employee_id', $request->employee_id);
            }
        }

        // Apply filters
        if ($request->filled('leave_type') && $request->leave_type !== 'all') {
            $query->where('leave_type', $request->leave_type);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Date Range Filter
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->where(function($q) use ($request) {
                $q->whereBetween('start_date', [$request->start_date, $request->end_date])
                  ->orWhereBetween('end_date', [$request->start_date, $request->end_date]);
            });
        }

        // Search Filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('id', $search)
                  ->orWhere('device_employee_id', $search);
            });
        }

        // Sorting
        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = $request->query('sort_order', 'desc');

        if ($sortBy === 'employee') {
            $query->join('employees', 'leaves.employee_id', '=', 'employees.id')
                  ->orderBy('employees.name', $sortOrder)
                  ->select('leaves.*');
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $leaves = $query->paginate(config('constants.data.pagination_count', 10))->withQueryString();

        return Inertia::render('Leave/Leaves', [
            'leaves' => $leaves,
            'filters' => $request->only(['employee_id', 'leave_type', 'status', 'start_date', 'end_date', 'search', 'sort_by', 'sort_order']),
            'employees' => isAdmin() ? Employee::select('id', 'name', 'device_employee_id')->get() : [],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $latestPayrollEndDate = \App\Models\Payroll::max('period_end');
        $employeePayrollDates = \App\Models\Payroll::select('employee_id', DB::raw('MAX(period_end) as max_period_end'))
            ->groupBy('employee_id')
            ->pluck('max_period_end', 'employee_id');

        return Inertia::render('Leave/LeaveCreate', [
            'employees' => Employee::select('id', 'name', 'device_employee_id')->get(),
            'leave_types' => ['Annual', 'Sick', 'Casual', 'Unpaid', 'Maternity', 'Paternity', 'Other'],
            'latest_payroll_end_date' => $latestPayrollEndDate,
            'employee_payroll_dates' => $employeePayrollDates,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required',
            'leave_type' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'half_day' => 'required|boolean',
            'reason' => 'required|string',
            'attachment' => 'nullable|file|max:2048',
            'status' => 'required|in:Pending,Approved,Rejected',
            'notes' => 'nullable|string',
        ]);

        $employeeIds = is_array($request->employee_id) ? $request->employee_id : [$request->employee_id];
        $employeeIds = array_filter($employeeIds, fn($v) => !is_null($v) && $v !== '');

        if (empty($employeeIds)) {
            return back()->withErrors(['employee_id' => 'Please select at least one employee.']);
        }

        // Prevent leave creation/update for processed payroll period
        $payrollQuery = \App\Models\Payroll::query();
        if (!in_array('all', $employeeIds)) {
            $payrollQuery->whereIn('employee_id', $employeeIds);
        }
        $latestPayrollEndDate = $payrollQuery->max('period_end');

        if ($latestPayrollEndDate && Carbon::parse($request->start_date)->lte(Carbon::parse($latestPayrollEndDate))) {
            return back()->withErrors(['start_date' => 'Leave cannot be added for a previous payroll period.']);
        }

        if (in_array('all', $employeeIds)) {
            $employees = Employee::all();
        } else {
            $employees = Employee::whereIn('id', $employeeIds)->get();
        }

        // Calculate days
        if ($request->half_day) {
            $totalDays = 0.5;
        } else {
            $start = Carbon::parse($request->start_date);
            $end = Carbon::parse($request->end_date);
            $totalDays = $start->diffInDays($end) + 1;
        }

        // File attachment handling
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('leave_attachments', 'public');
        }

        $createdCount = 0;
        $skippedCount = 0;

        foreach ($employees as $emp) {
            $overlapExists = Leave::where('employee_id', $emp->id)
                ->where(function ($query) use ($request) {
                    $query->whereBetween('start_date', [$request->start_date, $request->end_date])
                          ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                          ->orWhere(function ($q) use ($request) {
                              $q->where('start_date', '<=', $request->start_date)
                                ->where('end_date', '>=', $request->end_date);
                          });
                })
                ->exists();

            if ($overlapExists) {
                $skippedCount++;
                continue;
            }

            $leave = Leave::create([
                'employee_id' => $emp->id,
                'leave_type' => $request->leave_type,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'total_days' => $totalDays,
                'half_day' => $request->half_day,
                'reason' => $request->reason,
                'attachment_path' => $attachmentPath,
                'status' => $request->status,
                'notes' => $request->notes,
                'applied_by' => isAdmin() ? 'Admin' : 'Employee',
                'approved_by' => $request->status === 'Approved' ? auth()->user()->id : null,
                'approved_at' => $request->status === 'Approved' ? now() : null,
            ]);

            if ($leave->status === 'Approved') {
                $this->syncLeaveToAttendance($leave);
            }

            $createdCount++;
        }

        $message = "Leave record created for {$createdCount} employee(s).";
        if ($skippedCount > 0) {
            $message .= " ({$skippedCount} skipped due to date overlap)";
        }

        return redirect()->route('leaves.index')->with('success', $message);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $leave = Leave::with(['employee.department', 'employee.employeePositions.position', 'approver'])->findOrFail($id);

        if (!isAdmin() && $leave->employee_id !== auth()->user()->id) {
            abort(403, 'Unauthorized access.');
        }

        return Inertia::render('Leave/LeaveView', [
            'leave' => $leave
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $leave = Leave::findOrFail($id);

        if (Carbon::parse($leave->end_date)->lt(Carbon::today())) {
            return redirect()->route('leaves.index')->withErrors(['error' => 'Cannot edit a leave request after its end date has passed.']);
        }

        $latestPayrollEndDate = \App\Models\Payroll::max('period_end');
        $employeePayrollDates = \App\Models\Payroll::select('employee_id', DB::raw('MAX(period_end) as max_period_end'))
            ->groupBy('employee_id')
            ->pluck('max_period_end', 'employee_id');

        return Inertia::render('Leave/LeaveEdit', [
            'leave' => $leave,
            'employees' => Employee::select('id', 'name', 'device_employee_id')->get(),
            'leave_types' => ['Annual', 'Sick', 'Casual', 'Unpaid', 'Maternity', 'Paternity', 'Other'],
            'latest_payroll_end_date' => $latestPayrollEndDate,
            'employee_payroll_dates' => $employeePayrollDates,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $leave = Leave::findOrFail($id);

        if (Carbon::parse($leave->end_date)->lt(Carbon::today())) {
            return back()->withErrors(['error' => 'Cannot edit a leave request after its end date has passed.']);
        }

        $request->validate([
            'employee_id' => 'required',
            'leave_type' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'half_day' => 'required|boolean',
            'reason' => 'required|string',
            'attachment' => 'nullable',
            'status' => 'required|in:Pending,Approved,Rejected',
            'notes' => 'nullable|string',
        ]);

        $employeeIds = is_array($request->employee_id) ? $request->employee_id : [$request->employee_id];
        $employeeIds = array_filter($employeeIds, fn($v) => !is_null($v) && $v !== '');

        if (empty($employeeIds)) {
            return back()->withErrors(['employee_id' => 'Please select at least one employee.']);
        }

        // Prevent leave update for processed payroll period
        $payrollQuery = \App\Models\Payroll::query();
        if (!in_array('all', $employeeIds)) {
            $payrollQuery->whereIn('employee_id', $employeeIds);
        }
        $latestPayrollEndDate = $payrollQuery->max('period_end');

        if ($latestPayrollEndDate && Carbon::parse($request->start_date)->lte(Carbon::parse($latestPayrollEndDate))) {
            return back()->withErrors(['start_date' => 'Leave cannot be added for a previous payroll period.']);
        }

        if (in_array('all', $employeeIds)) {
            $employees = Employee::all();
        } else {
            $employees = Employee::whereIn('id', $employeeIds)->get();
        }

        // Calculate days
        if ($request->half_day) {
            $totalDays = 0.5;
        } else {
            $start = Carbon::parse($request->start_date);
            $end = Carbon::parse($request->end_date);
            $totalDays = $start->diffInDays($end) + 1;
        }

        // Attachment logic
        $attachmentPath = $leave->attachment_path;
        if ($request->hasFile('attachment')) {
            // Delete old file
            if ($leave->attachment_path) {
                Storage::disk('public')->delete($leave->attachment_path);
            }
            $attachmentPath = $request->file('attachment')->store('leave_attachments', 'public');
        }

        $createdCount = 0;
        $skippedCount = 0;

        foreach ($employees as $emp) {
            if ($emp->id == $leave->employee_id) {
                $oldStatus = $leave->status;
                $leave->update([
                    'leave_type' => $request->leave_type,
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'total_days' => $totalDays,
                    'half_day' => $request->half_day,
                    'reason' => $request->reason,
                    'attachment_path' => $attachmentPath,
                    'status' => $request->status,
                    'notes' => $request->notes,
                    'approved_by' => $request->status === 'Approved' ? auth()->user()->id : null,
                    'approved_at' => $request->status === 'Approved' ? now() : null,
                ]);

                if ($leave->status === 'Approved') {
                    $this->syncLeaveToAttendance($leave);
                } else if ($oldStatus === 'Approved') {
                    $this->removeLeaveFromAttendance($leave);
                }

                $createdCount++;
            } else {
                $overlapExists = Leave::where('employee_id', $emp->id)
                    ->where(function ($query) use ($request) {
                        $query->whereBetween('start_date', [$request->start_date, $request->end_date])
                              ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                              ->orWhere(function ($q) use ($request) {
                                  $q->where('start_date', '<=', $request->start_date)
                                    ->where('end_date', '>=', $request->end_date);
                              });
                    })
                    ->exists();

                if ($overlapExists) {
                    $skippedCount++;
                    continue;
                }

                $newLeave = Leave::create([
                    'employee_id' => $emp->id,
                    'leave_type' => $request->leave_type,
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'total_days' => $totalDays,
                    'half_day' => $request->half_day,
                    'reason' => $request->reason,
                    'attachment_path' => $attachmentPath,
                    'status' => $request->status,
                    'notes' => $request->notes,
                    'applied_by' => isAdmin() ? 'Admin' : 'Employee',
                    'approved_by' => $request->status === 'Approved' ? auth()->user()->id : null,
                    'approved_at' => $request->status === 'Approved' ? now() : null,
                ]);

                if ($newLeave->status === 'Approved') {
                    $this->syncLeaveToAttendance($newLeave);
                }

                $createdCount++;
            }
        }

        $message = "Leave updated and applied to {$createdCount} employee(s).";
        if ($skippedCount > 0) {
            $message .= " ({$skippedCount} skipped due to date overlap)";
        }

        return redirect()->route('leaves.index')->with('success', $message);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $leave = Leave::findOrFail($id);

        if ($leave->attachment_path) {
            Storage::disk('public')->delete($leave->attachment_path);
        }

        if ($leave->status === 'Approved') {
            $this->removeLeaveFromAttendance($leave);
        }

        $leave->delete();

        return redirect()->route('leaves.index')->with('success', 'Leave record deleted successfully.');
    }

    /**
     * Approve leave status.
     */
    public function approve(Request $request, string $id)
    {
        $leave = Leave::findOrFail($id);

        if (Carbon::parse($leave->end_date)->lt(Carbon::today())) {
            return back()->withErrors(['error' => 'Cannot approve a leave request after its end date has passed.']);
        }

        $leave->update([
            'status' => 'Approved',
            'notes' => $request->notes ?? $leave->notes,
            'approved_by' => auth()->user()->id,
            'approved_at' => now(),
        ]);

        $this->syncLeaveToAttendance($leave);

        return redirect()->back()->with('success', 'Leave approved successfully.');
    }

    /**
     * Reject leave status.
     */
    public function reject(Request $request, string $id)
    {
        $leave = Leave::findOrFail($id);

        if (Carbon::parse($leave->end_date)->lt(Carbon::today())) {
            return back()->withErrors(['error' => 'Cannot reject a leave request after its end date has passed.']);
        }

        $this->removeLeaveFromAttendance($leave);

        $leave->update([
            'status' => 'Rejected',
            'notes' => $request->notes ?? $leave->notes,
            'approved_by' => auth()->user()->id,
            'approved_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Leave rejected successfully.');
    }

    /**
     * Helper to sync approved leaves to attendance table.
     */
    private function syncLeaveToAttendance(Leave $leave)
    {
        $start = Carbon::parse($leave->start_date);
        $end = Carbon::parse($leave->end_date);

        for ($date = $start; $date->lte($end); $date->addDay()) {
            Attendance::updateOrCreate(
                [
                    'employee_id' => $leave->employee_id,
                    'date' => $date->toDateString(),
                ],
                [
                    'status' => 'leave',
                    'notes' => 'Approved leave: ' . $leave->leave_type,
                    'is_manually_filled' => true,
                ]
            );
        }
    }

    /**
     * Helper to remove synced leaves from attendance table.
     */
    private function removeLeaveFromAttendance(Leave $leave)
    {
        $start = Carbon::parse($leave->start_date);
        $end = Carbon::parse($leave->end_date);

        Attendance::where('employee_id', $leave->employee_id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('status', 'leave')
            ->delete();
    }
}
