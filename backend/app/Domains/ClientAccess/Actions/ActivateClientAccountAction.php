<?php

namespace App\Domains\ClientAccess\Actions;

use App\Domains\ClientAccess\Services\ClientAccess;
use App\Models\Client;
use App\Models\ClientAccount;
use Illuminate\Support\Facades\DB;
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
            if (! $client || ! in_array($client->status, ['activo', 'moroso'], true) || ! $client->branch?->is_active || ! $invitation || $invitation->consumed_at || $invitation->revoked_at || now()->gte($invitation->expires_at) || ClientAccount::where('client_id', $client->id)->exists()) {
                $this->invalid();
            }
            $account = ClientAccount::create(['client_id' => $client->id, 'password' => $password]);
            DB::table('client_activation_codes')->where('id', $invitation->id)->update(['consumed_at' => now()]);
            app(ClientAccess::class)->audit($client->id, $account->id, 'activation_consumed', $invitation->id, 'client');
        }, 3);
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['code' => 'El código no es válido o ya venció. Solicita uno nuevo en tu sucursal.']);
    }
}
