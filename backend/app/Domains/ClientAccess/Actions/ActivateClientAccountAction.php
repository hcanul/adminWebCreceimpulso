<?php

namespace App\Domains\ClientAccess\Actions;

use App\Domains\ClientAccess\Services\ClientAccess;
use App\Domains\Push\Services\PushDevices;
use App\Models\Client;
use App\Models\ClientAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class ActivateClientAccountAction
{
    public function execute(string $code, string $password): void
    {
        DB::transaction(function () use ($code, $password) {
            $hash = hash('sha256', $code);
            $candidate = DB::table('client_activation_codes')->where('code_hash', $hash)->first();
            if (! $candidate) {
                $this->invalid();
            }
            $client = Client::whereKey($candidate->client_id)->lockForUpdate()->first();
            $invitation = DB::table('client_activation_codes')->where('id', $candidate->id)->lockForUpdate()->first();
            if (! $client || ! in_array($client->status, ['activo', 'moroso'], true) || ! $client->branch?->is_active || ! $invitation || $invitation->consumed_at || $invitation->revoked_at || now()->gte($invitation->expires_at)) {
                $this->invalid();
            }
            $account = ClientAccount::where('client_id', $client->id)->lockForUpdate()->first();
            $recovery = $invitation->purpose === 'recovery';
            if ($recovery) {
                if (! $account || ! $account->is_active || $account->id !== $invitation->account_id || ! hash_equals($invitation->password_fingerprint ?? '', hash('sha256', $account->password))) {
                    $this->invalid();
                }
                if (Hash::check($password, $account->password)) {
                    throw ValidationException::withMessages(['password' => 'Elige una contraseña diferente de la anterior.']);
                }
                $account->update(['password' => $password, 'must_change_password' => false, 'temporary_password_expires_at' => null]);
                foreach ($account->tokens()->pluck('id') as $tokenId) {
                    app(PushDevices::class)->revokeSession((int) $tokenId);
                }
                $account->tokens()->delete();
            } else {
                if ($account || $invitation->purpose !== 'activation') {
                    $this->invalid();
                }
                $account = ClientAccount::create(['client_id' => $client->id, 'password' => $password]);
            }
            DB::table('client_activation_codes')->where('client_id', $client->id)->where('id', '!=', $invitation->id)->whereNull('consumed_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
            DB::table('client_activation_codes')->where('id', $invitation->id)->update(['consumed_at' => now()]);
            app(ClientAccess::class)->audit($client->id, $account->id, $recovery ? 'recovery_consumed' : 'activation_consumed', $invitation->id, 'client');
        }, 3);
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['code' => 'El código no es válido o ya venció. Solicita uno nuevo en tu sucursal.']);
    }
}
