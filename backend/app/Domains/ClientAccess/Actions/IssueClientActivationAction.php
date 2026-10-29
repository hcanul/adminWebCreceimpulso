<?php

namespace App\Domains\ClientAccess\Actions;

use App\Domains\ClientAccess\Services\ClientAccess;
use App\Models\ClientAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class IssueClientActivationAction
{
    public function execute(User $actor, string $clientId, bool $recovery = false): array
    {
        return DB::transaction(function () use ($actor, $clientId, $recovery) {
            $access = app(ClientAccess::class);
            $client = $access->clients($actor)->whereKey($clientId)->lockForUpdate()->firstOrFail();
            $account = ClientAccount::where('client_id', $client->id)->lockForUpdate()->first();
            if ($recovery) {
                abort_unless($account && $account->is_active, 409, 'La recuperación requiere una cuenta activa.');
            } else {
                abort_if($account, 409, 'Este cliente ya tiene una cuenta. Utiliza recuperación presencial.');
            }
            DB::table('client_activation_codes')->where('client_id', $client->id)->whereNull('consumed_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $code = bin2hex(random_bytes(16));
            $id = (string) Str::uuid();
            $expires = now()->addMinutes(30);
            DB::table('client_activation_codes')->insert(['id' => $id, 'client_id' => $client->id, 'issued_by' => $actor->id, 'purpose' => $recovery ? 'recovery' : 'activation', 'account_id' => $recovery ? $account->id : null, 'password_fingerprint' => $recovery ? hash('sha256', $account->password) : null, 'code_hash' => hash('sha256', $code), 'expires_at' => $expires, 'created_at' => now()]);
            $access->audit($client->id, $actor->id, $recovery ? 'recovery_issued' : 'activation_issued', $id);

            return ['code' => $code, 'expires_at' => $expires->toIso8601String()];
        }, 3);
    }
}
