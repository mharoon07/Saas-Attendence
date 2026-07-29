<?php

namespace App\Http\Controllers;

use App\Models\StockyDepartment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockyDepartmentController extends Controller
{
    /**
     * Calculate next department code
     */
    public static function getNextCode(): string
    {
        $codes = StockyDepartment::pluck('code')->toArray();
        $maxNumeric = 0;
        foreach ($codes as $code) {
            if (is_numeric($code)) {
                $val = intval($code);
                if ($val > $maxNumeric) {
                    $maxNumeric = $val;
                }
            }
        }

        $candidateNumber = $maxNumeric + 1;
        do {
            $candidate = str_pad($candidateNumber, 2, '0', STR_PAD_LEFT);
            $candidateNumber++;
        } while (in_array($candidate, $codes));

        return $candidate;
    }

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
        return Inertia::render('StockyDepartment/Create', [
            'nextCode' => static::getNextCode(),
        ]);
    }

    /**
     * Store a newly created Stocky department in storage.
     */
    public function store(Request $request)
    {
        if (!$request->has('department') && $request->has('name')) {
            $request->merge(['department' => $request->input('name')]);
        }

        if (!$request->filled('code')) {
            $request->merge(['code' => static::getNextCode()]);
        }

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

        return back()->with('success', __('Department Created Successfully'));
    }
}
