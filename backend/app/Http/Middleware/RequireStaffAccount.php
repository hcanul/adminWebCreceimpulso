<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

final class RequireStaffAccount
{
    public function handle(Request $request, Closure $next)
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->is_active && ! $actor->deleted_at, 403);

        return $next($request);
    }
}
