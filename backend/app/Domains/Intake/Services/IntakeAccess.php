<?php

namespace App\Domains\Intake\Services;

use App\Models\Client;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class IntakeAccess
{
    public function allowed(User $user): bool
    {
        return $user->is_active && ! $user->deleted_at && ($user->hasRole('Super Admin') || $user->can('intake.access'));
    }

    public function global(User $user): bool
    {
        return $user->hasAnyRole(['Super Admin', 'Gerente General']);
    }

    public function field(User $user): bool
    {
        return ! $user->hasAnyRole(['Super Admin', 'Gerente General', 'Gerente']);
    }

    public function teams(User $user): Builder
    {
        abort_unless($this->allowed($user), 403);
        $query = Team::query();
        if (! $this->global($user)) {
            $query->where('branch_id', $user->branch_id ?? '');
        }
        if ($this->field($user)) {
            if ($user->hasRole('Promotor')) {
                $query->where('promoter_id', $user->id)->whereHas('promoter', fn ($q) => $q->assignable());
            } else {
                $query->where('created_by', $user->id);
            }
        }

        return $query;
    }

    public function team(User $user, string $id): Team
    {
        return $this->teams($user)->whereKey($id)->firstOrFail();
    }

    public function client(User $user, Client $client): bool
    {
        if ($client->trashed() || $client->status === 'baja') {
            return false;
        }
        if ($this->global($user)) {
            return true;
        }
        if ($client->branch_id !== $user->branch_id) {
            return false;
        }
        if (! $this->field($user)) {
            return true;
        }

        return $this->teams($user)->whereHas('memberships', fn ($q) => $q->where('client_id', $client->id))->exists();
    }
}
