<?php

namespace App\Domains\Intake\Actions;

use App\Models\Branch;
use App\Models\Promoter;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RegisterPromoterAction
{
    public function execute(User $actor, string $userId): Promoter
    {
        abort_unless(($actor->hasRole('Super Admin') || $actor->can('promoters.register')), 403);

        return DB::transaction(function () use ($userId, $actor) {
            $user = User::whereKey($userId)->where('is_active', true)->whereNull('deleted_at')->lockForUpdate()->firstOrFail();
            abort_unless($actor->hasAnyRole(['Super Admin', 'Gerente General']) || $actor->branch_id === $user->branch_id, 403);
            abort_unless($user->hasRole('Promotor'), 422, 'Solo un usuario con rol Promotor puede darse de alta como promotor.');
            abort_unless(Branch::whereKey($user->branch_id)->where('is_active', true)->exists(), 422, 'El usuario requiere una sucursal activa asignada.');

            return Promoter::firstOrCreate(['id' => $user->id], ['branch_id' => $user->branch_id, 'is_active' => true]);
        });
    }
}
