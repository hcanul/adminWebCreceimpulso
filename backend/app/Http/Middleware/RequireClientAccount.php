<?php

namespace App\Http\Middleware;

use App\Models\ClientAccount;
use Closure;
use Illuminate\Http\Request;

final class RequireClientAccount
{
    public function handle(Request $request, Closure $next)
    {
        $account = $request->user();
        abort_unless($account instanceof ClientAccount, 403);
        abort_unless($account->is_active && $account->client && in_array($account->client->status, ['activo', 'moroso'], true) && $account->client->branch?->is_active, 401);
        abort_unless($account->tokenCan('client:read'), 403);

        return $next($request);
    }
}
