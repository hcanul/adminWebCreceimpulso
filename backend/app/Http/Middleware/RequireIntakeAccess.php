<?php

namespace App\Http\Middleware;

use App\Domains\Intake\Services\IntakeAccess;
use Closure;
use Illuminate\Http\Request;

final class RequireIntakeAccess
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user() && app(IntakeAccess::class)->allowed($request->user()), 403);

        return $next($request);
    }
}
