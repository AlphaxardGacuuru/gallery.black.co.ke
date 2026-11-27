<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateOptionally
{
    /**
     * Resolve $request->user() through Sanctum without requiring a login,
     * for pages guests can see too. Guests get null, signed-in users still
     * get their own state (e.g. which photos they liked).
     */
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('sanctum');

        return $next($request);
    }
}
