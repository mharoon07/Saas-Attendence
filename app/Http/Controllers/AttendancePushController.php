<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\Employee;
use App\Models\Attendance;
use Carbon\Carbon;

class AttendancePushController extends Controller
{
    public function handlePush(Request $request)
    {
        // Sirf empty GET skip karo (device polling) — data wali GET/POST dono process karo
        $hasBody = !empty($request->getContent());
        $hasAttlog = $request->query('table') === 'ATTLOG';

        if ($request->isMethod('GET') && !$hasBody && !$hasAttlog) {
            return response('OK', 200)
                ->header('Content-Type', 'text/plain');
        }

        $timestamp = now()->toDateTimeString();

        // Laravel Default Log mein bhi URL save karein
        Log::info('Attendance Machine Request: ' . $request->fullUrl());

        /*
        |--------------------------------------------------------------------------
        | Capture EVERYTHING
        |--------------------------------------------------------------------------
        |
        | Different ZKTeco/SenseFace firmware versions send data differently:
        |
        | - Raw body
        | - Query params
        | - Form-data
        | - POST fields
        |
        | We capture all possible formats.
        |
        */

        $data = [
            'timestamp' => $timestamp,
            'client_ip' => $request->ip(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'query_parameters' => $request->query(),
            'post_parameters' => $_POST,
            'request_all' => $request->all(),
            'raw_body' => $request->getContent(),
        ];

        /*
        |--------------------------------------------------------------------------
        | Save Full Logs
        |--------------------------------------------------------------------------
        */

        $formattedLog = 
            PHP_EOL .
            str_repeat('=', 100) .
            PHP_EOL .
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) .
            PHP_EOL .
            str_repeat('=', 100) .
            PHP_EOL;

        // Har request ke liye alag file — timestamp + random ID se unique naam
        $fileName = 'device_logs/' 
            . now()->format('Y-m-d_H-i-s') 
            . '_' . substr(uniqid(), -6) 
            . '.txt';

        $saveResult = 'NOT_ATTEMPTED';
        $saveError  = null;
        try {
            Storage::put($fileName, $formattedLog);
            $saveResult = Storage::exists($fileName) ? 'FILE_SAVED_OK' : 'PUT_OK_BUT_FILE_MISSING';
        } catch (\Exception $e) {
            $saveResult = 'EXCEPTION';
            $saveError  = $e->getMessage();
        }

        /*
        |--------------------------------------------------------------------------
        | Email Debug Logs
        |--------------------------------------------------------------------------
        */

        try {
            $recipients = [
                'muhammadharoon02002@gmail.com',
                'contactrehmanali@gmail.com'
            ];

            // Use saved file if available, else fall back to the in-memory log string
            // so a file-save failure never silently blocks the email
            if ($saveResult === 'FILE_SAVED_OK') {
                $logFileContent = Storage::disk('local')->get($fileName);
                $attachName     = basename($fileName);
            } else {
                $logFileContent = $formattedLog;
                $attachName     = 'fallback_' . now()->format('Y-m-d_H-i-s') . '.txt';
            }

            $htmlContent = "
                <div style='font-family: Arial, sans-serif;'>

                    <h2 style='color:#6b21a8;'>
                        New Attendance Device Request Received
                    </h2>

                    <table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse; width:100%;'>

                        <tr>
                            <td><strong>Timestamp</strong></td>
                            <td>{$timestamp}</td>
                        </tr>

                        <tr>
                            <td><strong>Client IP</strong></td>
                            <td>{$data['client_ip']}</td>
                        </tr>

                        <tr>
                            <td><strong>Method</strong></td>
                            <td>{$data['method']}</td>
                        </tr>

                        <tr>
                            <td><strong>URL</strong></td>
                            <td>{$data['url']}</td>
                        </tr>

                        <tr>
                            <td><strong>File Save Status</strong></td>
                            <td>{$saveResult}" . ($saveError ? " — {$saveError}" : "") . "</td>
                        </tr>

                    </table>

                    <h3 style='margin-top:20px;'>
                        Raw Body
                    </h3>

                    <pre style='background:#f3f4f6;padding:15px;border-radius:6px;'>
" . e($data['raw_body'] ?: '[EMPTY]') . "
                    </pre>

                </div>
            ";

            Mail::html($htmlContent, function ($message) use (
                $recipients,
                $timestamp,
                $logFileContent,
                $attachName
            ) {
                $message
                    ->to($recipients)
                    ->subject("Attendance Machine Push - {$timestamp}")
                    ->attachData(
                        $logFileContent,
                        $attachName
                    );
            });

        } catch (\Exception $e) {
            // Save full error with timestamp + trace so nothing is silently lost
            Storage::append(
                'device_mail_errors.txt',
                PHP_EOL . str_repeat('-', 60) . PHP_EOL
                . '[' . now()->toDateTimeString() . '] MAIL ERROR' . PHP_EOL
                . 'Message : ' . $e->getMessage() . PHP_EOL
                . 'File    : ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL
                . 'URL     : ' . $request->fullUrl() . PHP_EOL
                . 'Body    : ' . substr($request->getContent(), 0, 500) . PHP_EOL
                . str_repeat('-', 60) . PHP_EOL
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Process ZKTeco Attendance Log
        |--------------------------------------------------------------------------
        |
        | ATTLOG format (tab-separated):
        |   parts[0] = Device PIN (employee's enrolled ID on the machine)
        |   parts[1] = Punch timestamp (Y-m-d H:i:s)
        |   parts[2] = Verify type (1=Fingerprint, 4=Card, 15=Face, 255=Face)
        |   parts[3] = In/Out status (0=Check In, 1=Check Out, 4=OT In, 5=OT Out)
        |
        | We match parts[0] against employees.device_employee_id field.
        | We use parts[3] to determine sign-in vs sign-out directly from the device.
        |
        */
        if ($request->query('table') === 'ATTLOG') {
            $serialNumber = $request->query('SN') ?? $request->input('SN');
            $device = null;
            if (!empty($serialNumber)) {
                $device = \App\Models\Device::where('serial_number', $serialNumber)->first();
                if (!$device) {
                    Log::warning("ZKTeco: Request from unregistered device [SN: {$serialNumber}].");
                }
            }
            $deviceNote = $device ? "device={$device->name}" : "device_sn={$serialNumber}";

            $rawBody = $request->getContent();
            if (!empty($rawBody)) {
                $lines = explode("\n", trim($rawBody));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;

                    $parts = explode("\t", $line);
                    if (count($parts) < 2) continue;

                    $devicePin    = trim($parts[0]);
                    $timestampStr = trim($parts[1]);
                    $verifyType   = isset($parts[2]) ? (int) trim($parts[2]) : -1;
                    $inOutStatus  = isset($parts[3]) ? (int) trim($parts[3]) : -1;

                    // Verify type → human readable note
                    $verifyLabels = [
                        1   => 'Fingerprint',
                        4   => 'Card',
                        15  => 'Face',
                        255 => 'Fingerprint',
                    ];
                    $verifyLabel = $verifyLabels[$verifyType] ?? 'Unknown';

                    try {
                        $punchTime = Carbon::createFromFormat('Y-m-d H:i:s', $timestampStr);
                        $date      = $punchTime->toDateString();

                        // ✅ Match by database primary ID or device_employee_id (the PIN enrolled on the machine)
                        $employee = Employee::where('id', $devicePin)
                            ->orWhere('device_employee_id', $devicePin)
                            ->first();

                        if (!$employee) {
                            Log::warning("ZKTeco: No employee found for ID or device PIN [{$devicePin}].");
                            continue;
                        }

                        $attendance = Attendance::where('employee_id', $employee->id)
                            ->where('date', $date)
                            ->first();

                        // --- NOTE: device parts[3] flag is NOT reliable on many ZKTeco models ---
                        // (this device always sends 1 = Check Out for every punch)
                        // We log it but do NOT use it for sign-in/sign-out decision.
                        // Instead: first punch of the day = sign_in, next punch = sign_out.
                        $deviceFlagNote = "device_flag={$inOutStatus}";

                        if (!$attendance) {
                            // ── No record today → ALWAYS treat as Sign In ──────────────────
                            $shiftStartTime = null;
                            if ($employee->activeShift()) {
                                $shiftStartTime = Carbon::createFromFormat('H:i:s', $employee->activeShift()->start_time)
                                    ->setDateFrom($punchTime);
                            }

                            $globalSettings = \App\Models\Globals::first();
                            $lateMarginMinutes = $globalSettings ? $globalSettings->late_threshold_minutes : 15;
                            $status = 'on_time';
                            if ($shiftStartTime && $shiftStartTime->diffInMinutes($punchTime, false) > $lateMarginMinutes) {
                                $status = 'late';
                            }

                            Attendance::create([
                                'employee_id'  => $employee->id,
                                'date'         => $date,
                                'status'       => $status,
                                'sign_in_time' => $punchTime,
                                'device_in_id' => $device ? $device->id : null,
                                'notes'        => "Machine Punch (Sign In) [{$verifyLabel}] [{$deviceFlagNote}] [{$deviceNote}]",
                            ]);

                            Log::info("ZKTeco Sign In: employee={$employee->id} pin={$devicePin} time={$timestampStr} {$deviceFlagNote}");

                        } else {
                            // ── Record exists → decide sign-in update or sign-out ────────────
                            $existingSignIn  = $attendance->sign_in_time  ? Carbon::parse($attendance->sign_in_time)  : null;
                            $existingSignOut = $attendance->sign_off_time ? Carbon::parse($attendance->sign_off_time) : null;

                            if (!$existingSignIn) {
                                // No sign-in on existing record → fill it in
                                $attendance->sign_in_time = $punchTime;
                                $attendance->device_in_id = $device ? $device->id : null;
                                $attendance->notes = trim(($attendance->notes ?? '') . " | Machine Punch (Sign In) [{$verifyLabel}] [{$deviceFlagNote}] [{$deviceNote}]", ' | ');
                                $attendance->save();
                                Log::info("ZKTeco Sign In (backfill): employee={$employee->id} time={$timestampStr} {$deviceFlagNote}");

                            } elseif ($punchTime->gt($existingSignIn->copy()->addMinute())) {
                                // Punch is at least 1 minute AFTER sign-in → treat as Sign Out
                                // Only update if this punch is later than current sign-out
                                if (!$existingSignOut || $punchTime->gt($existingSignOut)) {
                                    $attendance->sign_off_time = $punchTime;
                                    $attendance->device_out_id = $device ? $device->id : null;
                                    $attendance->notes = trim(($attendance->notes ?? '') . " | Machine Punch (Sign Out) [{$verifyLabel}] [{$deviceFlagNote}] [{$deviceNote}]", ' | ');
                                    $attendance->save();
                                    Log::info("ZKTeco Sign Out: employee={$employee->id} time={$timestampStr} {$deviceFlagNote}");
                                } else {
                                    Log::info("ZKTeco Duplicate/Old punch ignored: employee={$employee->id} time={$timestampStr}");
                                }
                            } else {
                                // Punch is within 1 minute of sign-in → duplicate, ignore
                                Log::info("ZKTeco Duplicate punch ignored (within 1min of sign-in): employee={$employee->id} time={$timestampStr}");
                            }
                        }

                    } catch (\Exception $e) {
                        Log::error("ZKTeco Attendance parsing error for PIN [{$devicePin}]: " . $e->getMessage());
                    }
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT RESPONSE
        |--------------------------------------------------------------------------
        |
        | ZKTeco devices expect plain text response.
        | Never return JSON here.
        |
        */

        return response('OK', 200)
            ->header('Content-Type', 'text/plain');
    }
}
