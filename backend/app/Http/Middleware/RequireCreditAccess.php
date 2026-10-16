<?php

namespace App\Http\Middleware;

use App\Domains\Credit\Services\CreditAccess;
use Closure;
use Illuminate\Http\Request;

final class RequireCreditAccess
{
    public function handle(Request $request, Closure $next)
    {
        app(CreditAccess::class)->authorize($request->user());

        return $next($request);
    }
}
