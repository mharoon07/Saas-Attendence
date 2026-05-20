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
}
