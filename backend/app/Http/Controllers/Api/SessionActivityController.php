<?php

namespace App\Http\Controllers\Api;

use App\Domains\Auth\Actions\CheckSessionActivity;
use App\Http\Controllers\Controller;
use App\Models\ClientAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

final class SessionActivityController extends Controller
{
    public function __invoke(Request $request, CheckSessionActivity $action)
    {
        $user = $request->user();
        abort_unless(($user instanceof User || $user instanceof ClientAccount) && $user->is_active && ! $user->deleted_at, 401);
        $values = $request->validate(['idle_seconds' => ['required', 'integer', 'min:0', 'max:1799']]);
        $token = $user->currentAccessToken();
        abort_unless($token instanceof PersonalAccessToken, 401);
        if (! $action->execute($token, (int) $values['idle_seconds'])) {
            return response()->json(['message' => 'Tu sesión terminó por inactividad.', 'code' => 'SESSION_IDLE_EXPIRED'], 401)->header('Cache-Control', 'no-store');
        }

        return response()->noContent()->header('Cache-Control', 'no-store');
    }
}
