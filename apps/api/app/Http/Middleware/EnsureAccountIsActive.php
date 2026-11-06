<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_active) {
            $request->user()?->currentAccessToken()?->delete();

            return response()->json(['message' => 'Akun tidak aktif.'], 403);
        }

        return $next($request);
    }
}
