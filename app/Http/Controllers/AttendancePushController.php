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

            $logFileContent = Storage::disk('local')->get($fileName);

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
                $fileName
            ) {
                $message
                    ->to($recipients)
                    ->subject("Attendance Machine Push - {$timestamp}")
                    ->attachData(
                        $logFileContent,
                        basename($fileName)  // sirf file ka naam attach mein
                    );
            });

        } catch (\Exception $e) {
            Storage::append(
                'device_mail_errors.txt',
                $e->getMessage()
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

                        // --- Determine punch type using parts[3] ---
                        // 0 = Check In, 4 = OT In  → sign in
                        // 1 = Check Out, 5 = OT Out → sign out
                        // -1 = unknown              → fallback to time logic
                        $isCheckIn  = in_array($inOutStatus, [0, 4]);
                        $isCheckOut = in_array($inOutStatus, [1, 5]);

                        if (!$attendance) {
                            // No record for today — create a new sign-in record
                            $shiftStartTime = null;
                            if ($employee->activeShift()) {
                                $shiftStartTime = Carbon::createFromFormat('H:i:s', $employee->activeShift()->start_time)
                                    ->setDateFrom($punchTime);
                            }

                            $lateMarginMinutes = 15;
                            $status = 'on_time';
                            if ($shiftStartTime && $shiftStartTime->diffInMinutes($punchTime, false) > $lateMarginMinutes) {
                                $status = 'late';
                            }

                            Attendance::create([
                                'employee_id'  => $employee->id,
                                'date'         => $date,
                                'status'       => $status,
                                'sign_in_time' => $punchTime,
                                'notes'        => "Machine Punch (Sign In) [{$verifyLabel}]",
                            ]);

                        } else {
                            // Record exists — update sign-in or sign-out
                            $existingSignIn  = $attendance->sign_in_time  ? Carbon::parse($attendance->sign_in_time)  : null;
                            $existingSignOut = $attendance->sign_off_time ? Carbon::parse($attendance->sign_off_time) : null;

                            if ($isCheckIn || (!$isCheckOut && (!$existingSignIn || $punchTime->lt($existingSignIn)))) {
                                // Device says Check In, OR no sign-in yet, OR earlier punch
                                $attendance->sign_in_time = $punchTime;
                                $attendance->notes = trim(($attendance->notes ?? '') . " | Machine Punch (Sign In) [{$verifyLabel}]", ' | ');
                                $attendance->save();

                            } elseif ($isCheckOut || (!$isCheckIn && $existingSignIn && $punchTime->diffInMinutes($existingSignIn) > 5)) {
                                // Device says Check Out, OR no explicit flag but latest punch > 5min after sign-in
                                if (!$existingSignOut || $punchTime->gt($existingSignOut)) {
                                    $attendance->sign_off_time = $punchTime;
                                    $attendance->notes = trim(($attendance->notes ?? '') . " | Machine Punch (Sign Out) [{$verifyLabel}]", ' | ');
                                    $attendance->save();
                                }
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
