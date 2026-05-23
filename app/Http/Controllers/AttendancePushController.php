<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class AttendancePushController extends Controller
{
    /**
     * Handle incoming push requests from the attendance machine.
     * Logs all headers, query parameters, parsed body, and raw contents to a text file.
     * Then sends a detailed email report via Resend.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handlePush(Request $request)
    {
        $timestamp = Carbon::now()->toDateTimeString();
        $ip = $request->ip();
        $method = $request->method();
        $uri = $request->fullUrl();
        $headers = $request->headers->all();
        $queryParams = $request->query();
        $parsedBody = $request->all();
        $rawBody = $request->getContent();

        // Extract metadata for any uploaded files
        $filesMetadata = [];
        foreach ($request->allFiles() as $key => $file) {
            if (is_array($file)) {
                foreach ($file as $subKey => $subFile) {
                    $filesMetadata["{$key}.{$subKey}"] = [
                        'original_name' => $subFile->getClientOriginalName(),
                        'mime_type' => $subFile->getClientMimeType(),
                        'size_bytes' => $subFile->getSize(),
                    ];
                }
            } else {
                $filesMetadata[$key] = [
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size_bytes' => $file->getSize(),
                ];
            }
        }

        // Format the log entry beautifully
        $logEntry = str_repeat('=', 80) . PHP_EOL;
        $logEntry .= "TIMESTAMP  : " . $timestamp . PHP_EOL;
        $logEntry .= "CLIENT IP  : " . $ip . PHP_EOL;
        $logEntry .= "HTTP METHOD: " . $method . PHP_EOL;
        $logEntry .= "REQUEST URI: " . $uri . PHP_EOL;
        $logEntry .= str_repeat('-', 80) . PHP_EOL;
        
        $logEntry .= "HEADERS:" . PHP_EOL;
        $logEntry .= json_encode($headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        $logEntry .= str_repeat('-', 80) . PHP_EOL;

        $logEntry .= "QUERY PARAMETERS:" . PHP_EOL;
        $logEntry .= json_encode($queryParams, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        $logEntry .= str_repeat('-', 80) . PHP_EOL;

        $logEntry .= "PARSED BODY:" . PHP_EOL;
        $logEntry .= json_encode($parsedBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        $logEntry .= str_repeat('-', 80) . PHP_EOL;

        if (!empty($filesMetadata)) {
            $logEntry .= "UPLOADED FILES:" . PHP_EOL;
            $logEntry .= json_encode($filesMetadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            $logEntry .= str_repeat('-', 80) . PHP_EOL;
        }

        $logEntry .= "RAW BODY CONTENT:" . PHP_EOL;
        $logEntry .= (empty($rawBody) ? '[EMPTY]' : $rawBody) . PHP_EOL;
        $logEntry .= str_repeat('=', 80) . PHP_EOL . PHP_EOL;

        // Append the formatted entry to storage/app/attendance_device_logs.txt
        Storage::disk('local')->append('attendance_device_logs.txt', $logEntry);

        // Send report email using Laravel Mail
        $recipients = ['muhammadharoon02002@gmail.com', 'contactrehmanali@gmail.com'];

        if (!empty($recipients)) {
            try {
                // Get full log history for the email attachment
                $logFileContent = Storage::disk('local')->get('attendance_device_logs.txt');

                // Build detailed HTML email body
                $htmlContent = "<div style='font-family: Arial, sans-serif; max-width: 600px; border: 1px solid #ddd; padding: 20px; border-radius: 8px;'>";
                $htmlContent .= "<h2 style='color: #6b21a8; border-bottom: 2px solid #6b21a8; padding-bottom: 10px;'>⚠️ New Attendance Machine Push Data Received</h2>";
                $htmlContent .= "<p>A new POST request has been successfully captured and logged from your external attendance device.</p>";
                $htmlContent .= "<table style='width: 100%; border-collapse: collapse; margin-top: 15px;'>";
                $htmlContent .= "<tr style='background: #f8fafc;'><td style='padding: 8px; border: 1px solid #e2e8f0; font-weight: bold;'>Timestamp</td><td style='padding: 8px; border: 1px solid #e2e8f0;'>{$timestamp}</td></tr>";
                $htmlContent .= "<tr><td style='padding: 8px; border: 1px solid #e2e8f0; font-weight: bold;'>Client IP</td><td style='padding: 8px; border: 1px solid #e2e8f0;'>{$ip}</td></tr>";
                $htmlContent .= "<tr style='background: #f8fafc;'><td style='padding: 8px; border: 1px solid #e2e8f0; font-weight: bold;'>HTTP Method</td><td style='padding: 8px; border: 1px solid #e2e8f0;'>{$method}</td></tr>";
                $htmlContent .= "<tr><td style='padding: 8px; border: 1px solid #e2e8f0; font-weight: bold;'>Request URI</td><td style='padding: 8px; border: 1px solid #e2e8f0;'>{$uri}</td></tr>";
                $htmlContent .= "</table>";
                
                $htmlContent .= "<h4 style='color: #4b5563; margin-top: 20px;'>Raw Body / Payload Content:</h4>";
                $htmlContent .= "<pre style='background: #f3f4f6; color: #1f2937; padding: 12px; border: 1px solid #e5e7eb; border-radius: 4px; overflow-x: auto; max-height: 300px; font-family: monospace;'>" . e(empty($rawBody) ? '[EMPTY]' : $rawBody) . "</pre>";
                
                if (!empty($filesMetadata)) {
                    $htmlContent .= "<h4 style='color: #4b5563; margin-top: 15px;'>Uploaded Files:</h4>";
                    $htmlContent .= "<pre style='background: #f3f4f6; color: #1f2937; padding: 10px; border: 1px solid #e5e7eb; border-radius: 4px;'>" . e(json_encode($filesMetadata, JSON_PRETTY_PRINT)) . "</pre>";
                }

                $htmlContent .= "<p style='margin-top: 20px; font-size: 0.9em; color: #6b7280;'>Note: The complete running log file has been attached to this email as <strong>attendance_device_logs.txt</strong>.</p>";
                $htmlContent .= "</div>";

                Mail::html($htmlContent, function ($message) use ($recipients, $timestamp, $logFileContent) {
                    $message->to($recipients)
                            ->subject('⚠️ Attendance Machine Push: ' . $timestamp)
                            ->attachData($logFileContent, 'attendance_device_logs.txt');
                });
            } catch (\Exception $e) {
                // Log the exception to help with debugging email issues
                \Illuminate\Support\Facades\Log::error('Email sending failed: ' . $e->getMessage());
            }
        }

        return response('OK', 200)
            ->header('Content-Type', 'text/plain');
    }
}
