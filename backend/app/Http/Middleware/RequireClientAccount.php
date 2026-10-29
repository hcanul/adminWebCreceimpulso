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

        if ($account->must_change_password) {
            abort_unless($account->temporary_password_expires_at && now()->lt($account->temporary_password_expires_at), 401);
            if (! $request->is('api/client/me', 'api/client/logout', 'api/client/password')) {
                return response()->json(['message' => 'Cambia tu contraseña temporal antes de continuar.', 'code' => 'password_change_required'], 423)->header('Cache-Control', 'no-store');
            }
        }
        return $next($request);
    }
}
