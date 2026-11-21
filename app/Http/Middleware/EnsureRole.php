<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        if (! $request->user()?->hasRole(...$roles)) {
            return response()->json(['message' => 'Your role ('.$request->user()?->role_label.') is not allowed to do this.'], 403);
        }

        return $next($request);
    }
}
