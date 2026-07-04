<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
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
                ->select(['id', 'name', 'serial_number', 'ip_address'])
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
        $device = Device::create($res);

        if ($request->expectsJson()) {
            return response()->json([
                'device' => [
                    'id' => $device->id,
                    'serial_number' => $device->serial_number,
                    'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                ],
            ], 201);
        }

        return to_route('devices.index');
    }

    public function connectionStatus(Request $request, Device $device)
    {
        $since = null;
        if ($request->filled('since')) {
            try {
                $since = Carbon::parse($request->input('since'));
            } catch (\Exception $e) {
                $since = null;
            }
        }

        $lastSeenAt = $device->last_seen_at;
        $connected = $lastSeenAt && (!$since || $lastSeenAt->greaterThanOrEqualTo($since));

        return response()->json([
            'connected' => (bool) $connected,
            'last_seen_at' => $lastSeenAt?->toIso8601String(),
        ]);
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
