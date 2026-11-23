<?php

namespace App\Http\Middleware;

use App\Domains\Auth\Actions\CheckSessionActivity;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

final class RequireActiveSession
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->user()?->currentAccessToken();
        // These applications authenticate through personal access tokens. Transient
        // tokens used by Laravel's actingAs and cookie guards have no token row.
        if ($token instanceof PersonalAccessToken && $token->exists && ! app(CheckSessionActivity::class)->execute($token)) {
            return response()->json(['message' => 'Tu sesión terminó después de 30 minutos sin actividad. Vuelve a ingresar.', 'code' => 'SESSION_IDLE_EXPIRED'], 401)->header('Cache-Control', 'no-store');
        }

        return $next($request);
    }
}
