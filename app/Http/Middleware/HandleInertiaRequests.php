<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tightenco\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $this->sharedAuthUser($request),
            ],
            'ziggy' => function () use ($request) {
                return array_merge((new Ziggy)->toArray(), [
                    'location' => $request->url(),
                ]);
            },
            'ui' => $this->sharedUiCounts($request),
            'session' => [
                'update_in_progress' => session('update_in_progress'),
            ],
            'locale'=> config('app.locale'),
            'timezone'=> config('app.timezone'),
        ]);
    }

    protected function sharedAuthUser(Request $request): ?array
    {
        $user = $request->user();
        if (!$user) {
            return null;
        }

        try {
            return [
                'id' => $user->id,
                'name' => $user->name ?? trim(($user->firstname ?? '') . ' ' . ($user->lastname ?? '')),
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
            ];
        } catch (\Throwable $e) {
            return [
                'id' => $user->id,
                'name' => $user->email,
                'email' => $user->email,
                'roles' => [],
            ];
        }
    }

    protected function sharedUiCounts(Request $request): array
    {
        try {
            $reqCount = null;
            if ($request->user()) {
                $reqCount = isAdmin()
                    ? \App\Models\Request::where('status', 0)->count()
                    : \App\Models\Request::where('employee_id', auth()->user()->id)
                        ->where('status', '!=', 0)
                        ->where('is_seen', false)
                        ->count();
            }

            return [
                'empCount' => Employee::count(),
                'reqCount' => $reqCount,
            ];
        } catch (\Throwable $e) {
            return [
                'empCount' => 0,
                'reqCount' => null,
            ];
        }
    }
}
