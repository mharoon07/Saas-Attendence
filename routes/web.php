<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(['middleware' => ['role:admin', 'auth']], function () {

    Route::get('employees/find', [\App\Http\Controllers\EmployeeController::class, 'find'])->name('employees.find');
    Route::get('employees/archived', [\App\Http\Controllers\EmployeeController::class, 'archivedIndex'])->name('employees.archived');
    Route::resource('employees', \App\Http\Controllers\EmployeeController::class);
    Route::resource('branches', \App\Http\Controllers\BranchController::class);
    // Use StockyDepartmentController for the `/departments` routes (replace stocky-departments)
    Route::resource('departments', \App\Http\Controllers\StockyDepartmentController::class);
    Route::resource('positions', \App\Http\Controllers\PositionController::class);
    Route::resource('shifts', \App\Http\Controllers\ShiftController::class);
    Route::resource('metrics', \App\Http\Controllers\MetricsController::class);
    Route::resource('requests', \App\Http\Controllers\RequestController::class);
    Route::get('devices/{device}/connection-status', [\App\Http\Controllers\DeviceController::class, 'connectionStatus'])->name('devices.connection-status');
    Route::resource('devices', \App\Http\Controllers\DeviceController::class);

    // Loans, Advance Payments, & Cash Maintenance
    Route::resource('loans', \App\Http\Controllers\LoanController::class);
    Route::resource('advance-payments', \App\Http\Controllers\AdvancePaymentController::class);
    Route::resource('cash-transactions', \App\Http\Controllers\CashTransactionController::class);

    // Payroll
    Route::put('payrolls/{id}/updateStatus', [\App\Http\Controllers\PayrollController::class, 'updateStatus'])->name('payrolls.updateStatus');
    Route::put('payrolls/{id}/updateAttendance', [\App\Http\Controllers\PayrollController::class, 'updateAttendance'])->name('payrolls.updateAttendance');
    Route::get('payrolls/{id}/export', [\App\Http\Controllers\PayrollController::class, 'export'])->name('payrolls.export');
    Route::get('payrolls/{id}/pdf', [\App\Http\Controllers\PayrollController::class, 'pdf'])->name('payrolls.pdf');
    Route::get('payrolls/{id}/preview', [\App\Http\Controllers\PayrollController::class, 'preview'])->name('payrolls.preview');
    Route::resource('payrolls', \App\Http\Controllers\PayrollController::class);

    Route::get('attendance/{date}', [\App\Http\Controllers\AttendanceController::class, 'dayShow'])->name('attendance.show');
    Route::delete('attendance', [\App\Http\Controllers\AttendanceController::class, 'dayDelete'])->name('attendance.destroy');
    Route::resource('attendances', \App\Http\Controllers\AttendanceController::class);

    // Globals
    Route::get('globals', [\App\Http\Controllers\GlobalsController::class, 'index'])->name('globals.index');
    Route::get('globals/edit', [\App\Http\Controllers\GlobalsController::class, 'edit'])->name('globals.edit');
    Route::put('globals/edit', [\App\Http\Controllers\GlobalsController::class, 'update'])->name('globals.update');

    // Logs
    Route::get('logs',[\App\Http\Controllers\LogsController::class, 'index'])->name('logs.index');

    // Calendar
    Route::get('calendar', [\App\Http\Controllers\CalendarController::class, 'calendarIndex'])->name('calendar.index');
    // Leaves
    Route::put('leaves/{id}/approve', [\App\Http\Controllers\LeaveController::class, 'approve'])->name('leaves.approve');
    Route::put('leaves/{id}/reject', [\App\Http\Controllers\LeaveController::class, 'reject'])->name('leaves.reject');
    Route::resource('leaves', \App\Http\Controllers\LeaveController::class);
    Route::resource('calendars', \App\Http\Controllers\CalendarController::class);


});

// Logged
Route::group(['middleware' => ['auth']], function () {

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('reports', [\App\Http\Controllers\DashboardController::class, 'reportsIndex'])->name('reports.index');
    // Report downloads (CSV)
    Route::get('reports/payroll', [\App\Http\Controllers\ReportController::class, 'payroll'])->name('reports.payroll');
    Route::get('reports/attendance', [\App\Http\Controllers\ReportController::class, 'attendance'])->name('reports.attendance');
    Route::get('reports/late', [\App\Http\Controllers\ReportController::class, 'late'])->name('reports.late');
    Route::get('reports/loans', [\App\Http\Controllers\ReportController::class, 'loans'])->name('reports.loans');
    Route::get('reports/cash-transactions', [\App\Http\Controllers\ReportController::class, 'cashTransactions'])->name('reports.cash_transactions');
    Route::get('reports/advance-payments', [\App\Http\Controllers\ReportController::class, 'advancePayments'])->name('reports.advance_payments');

    Route::get('my-profile', [\App\Http\Controllers\EmployeeController::class, 'showMyProfile'])->name('my-profile');
    Route::resource('requests', \App\Http\Controllers\RequestController::class)->only(['index', 'show', 'create', 'store']);
    Route::resource('loans', \App\Http\Controllers\LoanController::class)->only(['index', 'show']);
    Route::resource('advance-payments', \App\Http\Controllers\AdvancePaymentController::class)->only(['index', 'show']);
    Route::resource('cash-transactions', \App\Http\Controllers\CashTransactionController::class)->only(['index', 'show']);
    Route::resource('leaves', \App\Http\Controllers\LeaveController::class)->only(['index', 'show']);
    Route::get('calendar', [\App\Http\Controllers\CalendarController::class, 'calendarIndex'])->name('calendar.index');

    Route::get('my-attendance', [\App\Http\Controllers\AttendanceController::class, 'attendanceDashboard'])->name('attendance.dashboard');
    Route::post('attendance/signin', [\App\Http\Controllers\AttendanceController::class, 'dashboardSignInAttendance'])->name('attendance.dashboardSignIn');
    Route::post('attendance/signoff', [\App\Http\Controllers\AttendanceController::class, 'dashboardSignOffAttendance'])->name('attendance.dashboardSignOff');

});

// Redirect authenticated users to the dashboard
Route::redirect('/', '/dashboard')->middleware('auth');

// Language Switching
Route::get('language/{language}', function ($language) {
    Session()->put('locale', $language);
    return redirect()->back();
})->name('language');

require __DIR__.'/auth.php';

// Helper route for database migrations on Hostinger shared hosting
Route::get('/run-migration', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        return 'Migration completed successfully!<br><pre>' . \Illuminate\Support\Facades\Artisan::output() . '</pre>';
    } catch (\Exception $e) {
        return 'Migration failed: ' . $e->getMessage();
    }
});

// Helper route for database starter seeding on Hostinger shared hosting
Route::get('/run-seeder', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'StarterSeeder', '--force' => true]);
        return 'Starter seeding completed successfully!<br><pre>' . \Illuminate\Support\Facades\Artisan::output() . '</pre>';
    } catch (\Exception $e) {
        return 'Starter seeding failed: ' . $e->getMessage();
    }
});

// Helper route for database dummy/test seeding on Hostinger shared hosting
Route::get('/run-dummy-seeder', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
        return 'Dummy seeding completed successfully!<br><pre>' . \Illuminate\Support\Facades\Artisan::output() . '</pre>';
    } catch (\Exception $e) {
        return 'Dummy seeding failed: ' . $e->getMessage();
    }
});

// Helper route for clearing all caches on Hostinger shared hosting
Route::get('/clear-cache', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('config:clear');
        \Illuminate\Support\Facades\Artisan::call('cache:clear');
        \Illuminate\Support\Facades\Artisan::call('route:clear');
        \Illuminate\Support\Facades\Artisan::call('view:clear');
        return 'All caches cleared successfully!<br><pre>' . \Illuminate\Support\Facades\Artisan::output() . '</pre>';
    } catch (\Exception $e) {
        return 'Cache clearing failed: ' . $e->getMessage();
    }
});

/*
|--------------------------------------------------------------------------
| ZKTeco / SenseFace ADMS Push Routes
|--------------------------------------------------------------------------
*/
Route::any('/iclock/cdata', [\App\Http\Controllers\AttendancePushController::class, 'handlePush']);
Route::any('/iclock/getrequest', [\App\Http\Controllers\AttendancePushController::class, 'handlePush']);
Route::any('/attendance-machine-push', [\App\Http\Controllers\AttendancePushController::class, 'handlePush']);

// TEMPORARY DEBUG ROUTE — delete after fixing
Route::get('/debug-storage', function () {
    $results = [];

    // 1. Storage path
    $results['storage_path'] = storage_path('app');

    // 2. Is writable?
    $results['is_writable'] = is_writable(storage_path('app'));

    // 3. Try writing a test file
    try {
        \Illuminate\Support\Facades\Storage::put('debug_test.txt', 'test write at ' . now());
        $results['write_test'] = 'SUCCESS — file created';
    } catch (\Exception $e) {
        $results['write_test'] = 'FAILED: ' . $e->getMessage();
    }

    // 4. Try writing to device_logs folder
    try {
        \Illuminate\Support\Facades\Storage::put('device_logs/debug_test.txt', 'test write at ' . now());
        $results['device_logs_write'] = 'SUCCESS — file created';
    } catch (\Exception $e) {
        $results['device_logs_write'] = 'FAILED: ' . $e->getMessage();
    }

    // 5. Check if AttendancePushController exists
    $results['controller_exists'] = class_exists(\App\Http\Controllers\AttendancePushController::class);

    // 6. PHP error log
    $results['php_version'] = PHP_VERSION;

    return response()->json($results, 200, [], JSON_PRETTY_PRINT);
});

// TEMPORARY DEBUG ROUTE — delete after fixing
Route::get('/debug-loan', function () {
    $results = [];
    
    // Check files
    $results['Loan_php_exists'] = file_exists(base_path('app/Models/Loan.php'));
    $results['loan_php_exists_lower'] = file_exists(base_path('app/Models/loan.php'));
    
    // List app/Models files
    $modelsPath = base_path('app/Models');
    $files = [];
    if (is_dir($modelsPath)) {
        foreach (scandir($modelsPath) as $file) {
            if ($file !== '.' && $file !== '..') {
                $files[] = $file;
            }
        }
    }
    $results['models_files'] = $files;
    
    // Check classmap
    $classmapPath = base_path('vendor/composer/autoload_classmap.php');
    if (file_exists($classmapPath)) {
        $classmap = require $classmapPath;
        $results['loan_in_classmap'] = isset($classmap['App\\Models\\Loan']) ? $classmap['App\\Models\\Loan'] : 'NOT FOUND';
        $results['loan_lower_in_classmap'] = isset($classmap['App\\Models\\loan']) ? $classmap['App\\Models\\loan'] : 'NOT FOUND';
    } else {
        $results['classmap_exists'] = false;
    }
    
    // Try running composer dump-autoload programmatically
    try {
        $results['composer_path'] = shell_exec('which composer 2>&1') ?: 'not found via which';
        $results['composer_dump'] = shell_exec('composer dump-autoload 2>&1') ?: 'no output';
    } catch (\Exception $e) {
        $results['composer_error'] = $e->getMessage();
    }
    
    return response()->json($results, 200, [], JSON_PRETTY_PRINT);
});

