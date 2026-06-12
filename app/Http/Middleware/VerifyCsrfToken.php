<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'api/iclock/cdata',
        'api/iclock/getrequest',
        'api/attendance-machine-push',
        'iclock/cdata',
        'iclock/getrequest',
        'attendance-machine-push',
    ];
}
