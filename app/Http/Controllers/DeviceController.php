<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\ValidationServices;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeviceController extends Controller
{
    protected ValidationServices $validationServices;

    public function __construct()
    {
        $this->validationServices = new ValidationServices;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return Inertia::render('Devices/Index', [
            'devices' => Device::when($request->term, function ($query, $term) {
                $query->where('name', 'ILIKE', '%' . $term . '%')
                    ->orWhere('serial_number', 'ILIKE', '%' . $term . '%')
                    ->orWhere('ip_address', 'ILIKE', '%' . $term . '%');
            })
                ->orderBy('id')
                ->paginate(config('constants.data.pagination_count')),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Devices/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $res = $this->validationServices->validateDeviceCreationDetails($request);
        Device::create($res);
        return to_route('devices.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $device = Device::findOrFail($id);
        return Inertia::render('Devices/Show', [
            'device' => $device
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        return Inertia::render('Devices/Edit', [
            'device' => Device::findOrFail($id)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $res = $this->validationServices->validateDeviceUpdateDetails($request, $id);
        Device::findOrFail($id)->update($res);
        return to_route('devices.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Device::findOrFail($id)->delete();
        return to_route('devices.index');
    }
}
