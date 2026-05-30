<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class AttendancePushController extends Controller
{
    public function handlePush(Request $request)
    {
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

        Storage::append('device_full_logs.txt', $formattedLog);

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

            $logFileContent = Storage::disk('local')->get('device_full_logs.txt');

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
                $logFileContent
            ) {
                $message
                    ->to($recipients)
                    ->subject("Attendance Machine Push - {$timestamp}")
                    ->attachData(
                        $logFileContent,
                        'device_full_logs.txt'
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
