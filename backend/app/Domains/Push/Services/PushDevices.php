<?php

namespace App\Domains\Push\Services;

use Illuminate\Support\Facades\DB;

final class PushDevices
{
    public function revokeAccount(string $id): void
    {
        DB::table('client_push_devices')->where('client_account_id', $id)->update(['enabled' => false, 'updated_at' => now()]);
    }

    public function revokeSession(?int $id): void
    {
        if ($id !== null) {
            DB::table('client_push_devices')->where('session_token_id', $id)->update(['enabled' => false, 'updated_at' => now()]);
        }
    }
}
