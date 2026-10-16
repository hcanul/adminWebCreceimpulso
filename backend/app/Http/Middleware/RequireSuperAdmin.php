<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class RequireSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user && $user->is_active && ! $user->deleted_at && $user->hasRole('Super Admin'), 403);

        return $next($request);
    }
}
