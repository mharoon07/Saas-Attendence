<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // 1. Attempt standard local authentication
        if (Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            $employee = Auth::user();
            try {
                $stockyUser = \App\Models\StockyUser::where('email', $employee->email)->first();
                if ($stockyUser) {
                    $isAdmin = $stockyUser->isAdministrator();
                    $roleName = $isAdmin ? 'admin' : 'employee';
                    if (!$employee->hasRole($roleName)) {
                        $employee->syncRoles([$roleName]);
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Stocky Sync Role On Local Login Error: ' . $e->getMessage());
            }

            RateLimiter::clear($this->throttleKey());
            return;
        }

        // 2. If local attempt fails, try fallback auth against Stocky database
        $email = $this->input('email');
        $password = $this->input('password');

        try {
            // Find user in Stocky database
            $stockyUser = \App\Models\StockyUser::where('email', $email)->first();

            if ($stockyUser && Hash::check($password, $stockyUser->password)) {
                // Find or dynamically create local Employee record
                $employee = \App\Models\Employee::where('email', $email)->first();

                if (!$employee) {
                    // Create dynamic Employee record
                    $fullName = trim(($stockyUser->firstname ?? '') . ' ' . ($stockyUser->lastname ?? ''));
                    if (empty($fullName)) {
                        $fullName = $stockyUser->username ?? 'Stocky User';
                    }

                    // Fallbacks for required unique phone & national_id fields
                    $phone = $stockyUser->phone;
                    if (empty($phone)) {
                        $phone = 'STOCKY-' . $stockyUser->id;
                    }
                    // Ensure unique phone in employees table (if already taken, append unique id)
                    if (\App\Models\Employee::where('phone', $phone)->exists()) {
                        $phone = $phone . '-' . time();
                    }

                    $nationalId = 'STOCKY-' . $stockyUser->id;
                    if (\App\Models\Employee::where('national_id', $nationalId)->exists()) {
                        $nationalId = $nationalId . '-' . time();
                    }

                    $defaultBranch = \App\Models\Branch::first();
                    $defaultDepartment = \App\Models\Department::first();

                    $employee = \App\Models\Employee::create([
                        'name' => $fullName,
                        'email' => $stockyUser->email,
                        'phone' => $phone,
                        'national_id' => $nationalId,
                        'password' => $stockyUser->password, // copy password hash from Stocky
                        'hired_on' => $stockyUser->created_at ? Carbon::parse($stockyUser->created_at)->toDateString() : Carbon::now()->toDateString(),
                        'is_remote' => false,
                        'branch_id' => $defaultBranch ? $defaultBranch->id : null,
                        'department_id' => $defaultDepartment ? $defaultDepartment->id : null,
                    ]);

                    // Assign a default Position if available
                    $defaultPosition = \App\Models\Position::first();
                    if ($defaultPosition) {
                        \App\Models\EmployeePosition::create([
                            'employee_id' => $employee->id,
                            'position_id' => $defaultPosition->id,
                            'start_date' => $employee->hired_on,
                        ]);
                    }

                    // Assign a default Shift if available
                    $defaultShift = \App\Models\Shift::first();
                    if ($defaultShift) {
                        \App\Models\EmployeeShift::create([
                            'employee_id' => $employee->id,
                            'shift_id' => $defaultShift->id,
                            'start_date' => $employee->hired_on,
                        ]);
                    }

                    // Assign a default Salary
                    \App\Models\EmployeeSalary::create([
                        'employee_id' => $employee->id,
                        'currency' => 'USD',
                        'salary' => 0,
                        'start_date' => $employee->hired_on,
                    ]);

                } else {
                    // If local employee exists but has outdated password hash compared to Stocky, sync it
                    if ($employee->password !== $stockyUser->password) {
                        $employee->update([
                            'password' => $stockyUser->password
                        ]);
                    }
                }

                // Sync roles based on whether the Stocky user is an administrator (covers both new and existing employees)
                $isAdmin = $stockyUser->isAdministrator();
                $roleName = $isAdmin ? 'admin' : 'employee';
                if (!$employee->hasRole($roleName)) {
                    $employee->syncRoles([$roleName]);
                }

                // Authenticate the employee
                Auth::login($employee, $this->boolean('remember'));
                RateLimiter::clear($this->throttleKey());
                return;
            }
        } catch (\Exception $e) {
            // Log connection issues or database errors, but fall through to standard error
            \Illuminate\Support\Facades\Log::error('Stocky Fallback Auth Error: ' . $e->getMessage());
        }

        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('These Credentials do not match our records.'),
        ]);
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->input('email')).'|'.$this->ip());
    }
}
