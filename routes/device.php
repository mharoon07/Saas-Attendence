<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ZKTeco / SenseFace ADMS Device Routes
|--------------------------------------------------------------------------
|
| These routes handle ZKTeco biometric device push requests.
|
| WHY SEPARATE FILE?
| - ZKTeco devices hit /iclock/cdata directly (no /api prefix)
| - Devices do NOT send CSRF tokens, so web.php would block POST requests
| - This file is loaded WITHOUT CSRF middleware in RouteServiceProvider
|
| ENDPOINTS:
| - POST/GET /iclock/cdata        ← device pushes attendance logs
| - GET      /iclock/getrequest   ← device polls for commands
|
*/

Route::any('/iclock/cdata', [\App\Http\Controllers\AttendancePushController::class, 'handlePush']);
Route::any('/iclock/getrequest', [\App\Http\Controllers\AttendancePushController::class, 'handlePush']);
Route::any('/attendance-machine-push', [\App\Http\Controllers\AttendancePushController::class, 'handlePush']);
