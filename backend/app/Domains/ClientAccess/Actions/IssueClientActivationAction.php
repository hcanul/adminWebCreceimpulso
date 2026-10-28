<?php

namespace App\Domains\ClientAccess\Actions;

use App\Domains\ClientAccess\Services\ClientAccess;
use App\Models\ClientAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class IssueClientActivationAction
{
    public function execute(User $actor, string $clientId): array
    {
        return DB::transaction(function () use ($actor, $clientId) {
            $access = app(ClientAccess::class);
            $client = $access->clients($actor)->whereKey($clientId)->lockForUpdate()->firstOrFail();
            abort_if(ClientAccount::where('client_id', $client->id)->exists(), 409, 'Este cliente ya tiene una cuenta. No se puede reemplazar su contraseña mediante una nueva activación.');
            DB::table('client_activation_codes')->where('client_id', $client->id)->whereNull('consumed_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $code = bin2hex(random_bytes(16));
            $id = (string) Str::uuid();
            $expires = now()->addMinutes(30);
            DB::table('client_activation_codes')->insert(['id' => $id, 'client_id' => $client->id, 'issued_by' => $actor->id, 'code_hash' => hash('sha256', $code), 'expires_at' => $expires, 'created_at' => now()]);
            $access->audit($client->id, $actor->id, 'activation_issued', $id);

            return ['code' => $code, 'expires_at' => $expires->toIso8601String()];
        }, 3);
    }
}
