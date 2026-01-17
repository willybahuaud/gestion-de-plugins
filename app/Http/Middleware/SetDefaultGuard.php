<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetDefaultGuard
{
    /**
     * Set the default guard for the request.
     *
     * This is needed for packages like laragear/webauthn that use
     * $request->user() without specifying a guard.
     */
    public function handle(Request $request, Closure $next, string $guard): Response
    {
        Auth::shouldUse($guard);

        return $next($request);
    }
}
