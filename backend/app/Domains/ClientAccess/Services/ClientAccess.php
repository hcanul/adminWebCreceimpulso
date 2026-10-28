<?php

namespace App\Domains\ClientAccess\Services;

use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ClientAccess
{
    public function clients(User $actor)
    {
        abort_unless($actor->is_active && ! $actor->deleted_at && $actor->hasAnyRole(['Super Admin', 'Gerente General', 'Gerente']), 403);
        $query = Client::query()->whereIn('status', ['activo', 'moroso'])->whereHas('branch', fn ($q) => $q->where('is_active', true));
        if (! $actor->hasAnyRole(['Super Admin', 'Gerente General'])) {
            $query->where('branch_id', $actor->branch_id ?? '');
        }

        return $query;
    }

    public function audit(string $clientId, string $actorId, string $action, string $codeId, string $actorType = 'staff'): void
    {
        DB::table('access_audits')->insert(['id' => (string) Str::uuid(), 'actor_id' => $actorId, 'subject_type' => 'client_access', 'subject_id' => $clientId, 'action' => $action, 'changes' => json_encode(['activation_id' => $codeId, 'actor_type' => $actorType], JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
