<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| ZKTeco / SenseFace ADMS Push Routes
|--------------------------------------------------------------------------
|
| Most ZKTeco devices automatically hit:
|   /iclock/cdata
| Some firmware versions also hit:
|   /iclock/getrequest
|
| We support both routes.
|
*/

Route::any('/iclock/cdata', [\App\Http\Controllers\AttendancePushController::class,'handlePush']);

Route::any('/iclock/getrequest', [\App\Http\Controllers\AttendancePushController::class,'handlePush']);

/*
|--------------------------------------------------------------------------
| Optional Custom Testing Route
|--------------------------------------------------------------------------
|
| This route is only for Postman/manual testing.
|
*/

Route::any('/attendance-machine-push', [\App\Http\Controllers\AttendancePushController::class,'handlePush']);
