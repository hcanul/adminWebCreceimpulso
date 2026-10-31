<?php

namespace App\Http\Controllers\Api;

use App\Domains\ClientAccess\Actions\LoginClientAction;
use App\Domains\ClientAccess\Services\ClientAccess;
use App\Domains\Push\Services\PushDevices;
use App\Http\Controllers\Controller;
use App\Models\ClientAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

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
            'password' => ['required', 'string', 'max:128', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);
        DB::transaction(function () use ($request, $data) {
            $account = ClientAccount::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($account->is_active, 401);
            if ($account->must_change_password) {
                abort_unless($account->temporary_password_expires_at && now()->lt($account->temporary_password_expires_at), 401);
            }
            if (! Hash::check($data['current_password'], $account->password)) {
                throw ValidationException::withMessages(['current_password' => 'La contraseña actual no coincide.']);
            }
            if (Hash::check($data['password'], $account->password)) {
                throw ValidationException::withMessages(['password' => 'Elige una contraseña diferente de la anterior.']);
            }
            $account->update(['password' => $data['password'], 'must_change_password' => false, 'temporary_password_expires_at' => null]);
            foreach ($account->tokens()->pluck('id') as $tokenId) {
                app(PushDevices::class)->revokeSession((int) $tokenId);
            }
            $account->tokens()->delete();
            DB::table('client_activation_codes')->where('client_id', $account->client_id)->whereNull('consumed_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
            app(ClientAccess::class)->audit($account->client_id, $account->id, 'password_changed', $account->id, 'client');
        }, 3);

        return response()->noContent()->header('Cache-Control', 'no-store');
    }

    public function logout(Request $request)
    {
        app(PushDevices::class)->revokeAccount($request->user()->id);
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
