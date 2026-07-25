<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Services\AttendanceServices;
use App\Services\CommonServices;
use App\Services\ValidationServices;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    protected AttendanceServices $attendanceServices;
    protected ValidationServices $validationServices;
    protected CommonServices $commonServices;
    public function __construct()
    {
        $this->attendanceServices = new AttendanceServices;
        $this->validationServices = new ValidationServices;
        $this->commonServices = new CommonServices;
    }

    /**
     * Display a listing of the resource.
     */
    public function attendanceDashboard()
    {
        return Inertia::render('Attendance/AttendanceDashboard', [
            "EmployeeStats" => auth()->user()->myStats(),
        ]);
    }

    public function index(Request $request)
    {
        $request->validate([
            'term' => 'nullable|date_format:Y-m-d',
        ]);
        $dateParam = $request->input('term', '');

        if ($dateParam) {
            $date = Carbon::createFromFormat('Y-m-d', $dateParam)->startOfDay();
            if ($date->isAfter(Carbon::today()))
                return response()->json(['Error' => 'Date cannot be in the future. Go back and choose a date before today.']);
            if ($date->month !== Carbon::today()->month || $date->year !== Carbon::today()->year)
                return response()->json(['Error' => 'Manual attendance marking is only allowed for the current month.']);

            $date = $date->toDateString();
        } else {
            $date = '';
        }

        $attendanceList = Attendance::select('date',
            DB::raw('COUNT(CASE WHEN status IN (\'late\', \'on_time\') THEN 1 END) as attended_count'),
            DB::raw('COUNT(CASE WHEN status = \'on_time\' THEN 1 END) as on_time_count'),
            DB::raw('COUNT(CASE WHEN status = \'late\' THEN 1 END) as late_count'),
            DB::raw('COUNT(CASE WHEN status = \'missed\' THEN 1 END) as missed_count')
        )->groupBy('date')->orderByDesc('date');

        if ($date)
            $attendanceList->where('date', '=', $date);

        return Inertia::render('Attendance/Attendances', [
            "attendanceList" => $attendanceList->paginate(config('constants.data.pagination_count')),
            "dateParam" => $date,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $request->validate([
            'term' => 'nullable|date_format:Y-m-d',
            'shift_id' => 'nullable|integer',
        ]);

        if ($request->term) {
            $date = Carbon::createFromFormat('Y-m-d', urldecode($request->term))->startOfDay();
            if ($date->isAfter(Carbon::today())) {
                return response()->json(['message' => 'Date cannot be in the future. Go back and choose a date before today.']);
            }
            if ($date->month !== Carbon::today()->month || $date->year !== Carbon::today()->year) {
                return response()->json(['message' => 'Manual attendance creation is only allowed for the current month.']);
            }
            $date = $date->toDateString();
        } else {
            $date = Carbon::today()->toDateString();
        }

        $attendanceList = Attendance::with('employee:employees.id,name')->where('date', $date)->orderBy('id')->get();
        $attendable = !$this->commonServices->isDayOff($date);

        $employeesQuery = Employee::with(['employeeShifts' => function ($query) {
            $query->whereNull('end_date')->with('shift');
        }])->where('hired_on', '<=', $date);

        if ($request->filled('shift_id')) {
            $employeesQuery->whereHas('employeeShifts', function ($q) use ($request) {
                $q->where('shift_id', $request->shift_id)->whereNull('end_date');
            });
        }

        $employees = $employeesQuery->orderBy('id')->select(['id', 'name'])->get();
        $shifts = \App\Models\Shift::get();

        return Inertia::render('Attendance/AttendanceCreate', [
            "dateParam" => $request->term ?? Carbon::today()->toDateString(),
            "shiftParam" => $request->shift_id ?? '',
            "employees" => $employees,
            "attendances" => $attendanceList,
            "attendable" => $attendable,
            "shifts" => $shifts,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $res = $this->validationServices->validateMassAttendanceCreation($request);
        return $this->attendanceServices->createAttendance($res, $this->commonServices);
    }

    public function dayShow(string $day)
    {
        $date = $this->validationServices->validateDayAttendanceDateParameter($day);
        if (!is_string($date)) // ERROR
            return $date; // Error Message

        $attendanceList = Attendance::where('date', $date)
            ->join('employees', 'attendances.employee_id', '=', 'employees.id')
            ->leftJoin('devices as di', 'attendances.device_in_id', '=', 'di.id')
            ->leftJoin('devices as do', 'attendances.device_out_id', '=', 'do.id')
            ->select([
                'attendances.id',
                'employees.name as employee_name',
                'attendances.status',
                'attendances.sign_in_time',
                'attendances.sign_off_time',
                'di.name as device_in_name',
                'do.name as device_out_name',
                'attendances.notes'
            ])
            ->orderByDesc('attendances.created_at')->paginate(config('constants.data.pagination_count'));

        return Inertia::render('Attendance/AttendanceDayView', [
            "attendanceList" => $attendanceList,
            "day" => $date
        ]);
    }

    public function dayDelete(Request $request)
    {
        $res = $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);
        $date = Carbon::createFromFormat('Y-m-d', urldecode($res['date']))->startOfDay();
        if ($date->month !== Carbon::today()->month || $date->year !== Carbon::today()->year) {
            return response()->json(['message' => 'Cannot delete attendance records for past months.']);
        }
        return $this->attendanceServices->deleteDayAttendance($res);
    }

    /***
     **************** SELF-TAKING ATTENDANCE SECTION ****************
     ***/

    public function dashboardSignInAttendance(Request $request)
    {
        return $this->attendanceServices->selfSignInAttendance($request);
    }

    public function dashboardSignOffAttendance(Request $request)
    {
        return $this->attendanceServices->selfSignOffAttendance($request);
    }

}
