<?php

namespace App\Domains\Messages\Services;

use App\Models\ClientAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class MessageAccess
{
    public function identity($actor): array
    {
        return [$actor instanceof ClientAccount ? 'client' : 'staff', $actor->id];
    }

    public function authorizeSend(User $actor): void
    {
        abort_unless($actor->is_active && ! $actor->deleted_at && ($actor->hasRole('Super Admin') || $actor->can('messages.send')), 403);
    }

    public function recipients(User $actor, string $type)
    {
        $this->authorizeSend($actor);
        if ($type === 'client') {
            $q = DB::table('client_accounts as a')->join('clients as c', 'c.id', '=', 'a.client_id')->join('branches as b', 'b.id', '=', 'c.branch_id')->where('a.is_active', true)->whereNull('c.deleted_at')->whereIn('c.status', ['activo', 'moroso'])->where('b.is_active', true)->whereNull('b.deleted_at')->select(['a.id', 'c.branch_id', 'c.first_name as name', 'c.last_name', 'c.folio as identifier', 'b.name as branch']);
        } else {
            $q = DB::table('users as a')->leftJoin('branches as b', 'b.id', '=', 'a.branch_id')->where('a.is_active', true)->whereNull('a.deleted_at')->where(fn ($w) => $w->whereNull('a.branch_id')->orWhere(fn ($v) => $v->where('b.is_active', true)->whereNull('b.deleted_at')))->selectRaw('a.id, a.branch_id, a.name, a.email as identifier, b.name as branch');
        }
        if (! $actor->hasAnyRole(['Super Admin', 'Gerente General'])) {
            $q->where($type === 'client' ? 'c.branch_id' : 'a.branch_id', $actor->branch_id ?? '');
        }

        return $q;
    }

    public function active(string $type, string $id): bool
    {
        if ($type === 'client') {
            $a = ClientAccount::with('client.branch')->find($id);

            return $a && $a->is_active && ! $a->must_change_password && $a->client && in_array($a->client->status, ['activo', 'moroso']) && $a->client->branch?->is_active;
        }
        $a = User::find($id);

        return $a && $a->is_active && ! $a->deleted_at && (! $a->branch_id || DB::table('branches')->where('id', $a->branch_id)->where('is_active', true)->whereNull('deleted_at')->exists());
    }
}
