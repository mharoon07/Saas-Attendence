<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class AttendancePushController extends Controller
{
    public function handlePush(Request $request)
    {
        $timestamp = now()->toDateTimeString();

        $data = [
            'time' => $timestamp,
            'ip' => $request->ip(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'query' => $request->query(),
            'post' => $_POST,
            'request_all' => $request->all(),
            'raw_body' => $request->getContent(),
        ];

        Storage::append(
            'device_full_logs.txt',
            json_encode($data, JSON_PRETTY_PRINT)
            . PHP_EOL .
            str_repeat('=', 100)
            . PHP_EOL
        );

         $recipients = ['muhammadharoon02002@gmail.com', 'contactrehmanali@gmail.com'];

        if (!empty($recipients)) {
            try {
             
                $logFileContent = Storage::disk('local')->get('device_full_logs.txt');
 
                $htmlContent = "<div style='font-family: Arial, sans-serif; max-width: 600px; border: 1px solid #ddd; padding: 20px; border-radius: 8px;'>";
                $htmlContent .= "<h2 style='color: #6b21a8; border-bottom: 2px solid #6b21a8; padding-bottom: 10px;'>⚠️ New Attendance Machine Push Data Received</h2>";
                $htmlContent .= "<p>A new POST request has been successfully captured and logged from your external attendance device.</p>";
                $htmlContent .= "<table style='width: 100%; border-collapse: collapse; margin-top: 15px;'>";
                $htmlContent .= "<tr style='background: #f8fafc;'><td style='padding: 8px; border: 1px solid #e2e8f0; font-weight: bold;'>Timestamp</td><td style='padding: 8px; border: 1px solid #e2e8f0;'>{$timestamp}</td></tr>";
                $htmlContent .= "<tr><td style='padding: 8px; border: 1px solid #e2e8f0; font-weight: bold;'>Client IP</td><td style='padding: 8px; border: 1px solid #e2e8f0;'>{$data['ip']}</td></tr>";
                $htmlContent .= "<tr style='background: #f8fafc;'><td style='padding: 8px; border: 1px solid #e2e8f0; font-weight: bold;'>HTTP Method</td><td style='padding: 8px; border: 1px solid #e2e8f0;'>{$data['method']}</td></tr>";
                $htmlContent .= "<tr><td style='padding: 8px; border: 1px solid #e2e8f0; font-weight: bold;'>Request URI</td><td style='padding: 8px; border: 1px solid #e2e8f0;'>{$data['url']}</td></tr>";
                $htmlContent .= "</table>";
                $htmlContent .= "<h4 style='color: #4b5563; margin-top: 20px;'>Raw Body / Payload Content:</h4>";
                $htmlContent .= "<pre style='background: #f3f4f6; color: #1f2937; padding: 12px; border: 1px solid #e5e7eb; border-radius: 4px; overflow-x: auto; max-height: 300px; font-family: monospace;'>" . e(empty($data['raw_body']) ? '[EMPTY]' : $data['raw_body']) . "</pre>";
                $htmlContent .= "<p style='margin-top: 20px; font-size: 0.9em; color: #6b7280;'>Note: The complete running log file has been attached to this email as <strong>device_full_logs.txt</strong>.</p>";
                $htmlContent .= "</div>";
                \Illuminate\Support\Facades\Mail::html($htmlContent, function ($message) use ($recipients, $timestamp, $logFileContent) {
                    $message->to($recipients)
                            ->subject('⚠️ Attendance Machine Push: ' . $timestamp)
                            ->attachData($logFileContent, 'device_full_logs.txt');
                });
            } catch (\Exception $e) {
             }
        }

        return response('OK', 200)
            ->header('Content-Type', 'text/plain');
    }
}
