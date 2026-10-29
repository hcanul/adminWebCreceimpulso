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

        $recovery = ($request->isMethod('GET') && $request->path() === 'api/me')
            || ($request->isMethod('POST') && in_array($request->path(), ['api/password', 'api/logout'], true));
        if ($actor->password_change_required && ! $recovery) {
            return response()->json(['message' => 'Tu contraseña venció. Captura una nueva para continuar.', 'code' => 'PASSWORD_CHANGE_REQUIRED'], 403);
        }

        return $next($request);
    }
}
