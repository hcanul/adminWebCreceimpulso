<?php

namespace App\Http\Controllers\Api;

use App\Domains\ClientAccess\Actions\LoginClientAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

final class ClientSessionController extends Controller
{
    public function login(Request $request, LoginClientAction $action)
    {
        $data = $request->validate(['identifier' => ['required', 'string', 'max:80'], 'password' => ['required', 'string', 'max:128']]);

        return response()->json($action->execute($data['identifier'], $data['password']))->header('Cache-Control', 'no-store');
    }

    public function me(Request $request)
    {
        $client = $request->user()->client;

        return response()->json(['must_change_password' => $request->user()->must_change_password, 'name' => $client->first_name.' '.$client->last_name, 'folio' => $client->folio, 'branch' => $client->branch->name])->header('Cache-Control', 'no-store');
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'max:128'],
            'password' => ['required', 'string', 'max:128', 'confirmed', \Illuminate\Validation\Rules\Password::min(12)->letters()->numbers()],
        ]);
        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $data) {
            $account = \App\Models\ClientAccount::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($account->is_active, 401);
            if ($account->must_change_password) {
                abort_unless($account->temporary_password_expires_at && now()->lt($account->temporary_password_expires_at), 401);
            }
            if (! \Illuminate\Support\Facades\Hash::check($data['current_password'], $account->password)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['current_password' => 'La contraseña actual no coincide.']);
            }
            if (\Illuminate\Support\Facades\Hash::check($data['password'], $account->password)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['password' => 'Elige una contraseña diferente de la anterior.']);
            }
            $account->update(['password' => $data['password'], 'must_change_password' => false, 'temporary_password_expires_at' => null]);
            foreach ($account->tokens()->pluck('id') as $tokenId) {
                app(\App\Domains\Push\Services\PushDevices::class)->revokeSession((int) $tokenId);
            }
            $account->tokens()->delete();
            \Illuminate\Support\Facades\DB::table('client_activation_codes')->where('client_id', $account->client_id)->whereNull('consumed_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
            app(\App\Domains\ClientAccess\Services\ClientAccess::class)->audit($account->client_id, $account->id, 'password_changed', $account->id, 'client');
        }, 3);
        return response()->noContent()->header('Cache-Control', 'no-store');
    }

    public function logout(Request $request)
    {
        app(\App\Domains\Push\Services\PushDevices::class)->revokeSession($request->user()->currentAccessToken()?->id);
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
