<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (! $request->user()?->hasRole($role)) {
            return response()->json(['message' => 'Akses tidak diizinkan.'], 403);
        }

        return $next($request);
    }
}
