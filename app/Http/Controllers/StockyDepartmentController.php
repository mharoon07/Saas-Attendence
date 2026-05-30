<?php

namespace App\Http\Controllers;

use App\Models\StockyDepartment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockyDepartmentController extends Controller
{
    /**
     * Display a listing of the Stocky departments.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('StockyDepartment/Index', [
            'departments' => StockyDepartment::when($request->term, function ($query, $term) {
                $query->where('department', 'LIKE', '%' . $term . '%')
                      ->orWhere('code', 'LIKE', '%' . $term . '%');
            })
                ->select(['id', 'department', 'code'])
                ->orderBy('id')
                ->paginate(config('constants.data.pagination_count')),
            'filters' => $request->only(['term']),
        ]);
    }

    /**
     * Show the form for creating a new Stocky department.
     */
    public function create(): Response
    {
        $maxCode = \Illuminate\Support\Facades\DB::connection('stocky')
            ->table('departments')
            ->max('code');

        $nextCodeNumber = $maxCode && is_numeric($maxCode) ? intval($maxCode) + 1 : 1;
        $nextCode = str_pad($nextCodeNumber, 2, '0', STR_PAD_LEFT);

        return Inertia::render('StockyDepartment/Create', [
            'nextCode' => $nextCode,
        ]);
    }

    /**
     * Store a newly created Stocky department in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'department' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('stocky.departments', 'department')
            ],
            'code' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('stocky.departments', 'code')
            ],
        ]);

        StockyDepartment::create([
            'department' => $validated['department'],
            'code' => $validated['code'],
            'user_id' => auth()->id(),
            'department_head' => null,
        ]);

        return to_route('departments.index');
    }
}
