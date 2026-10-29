<?php

namespace App\Domains\Administration\Actions;

use App\Domains\Administration\DTOs\UserData;
use App\Models\Promoter;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SaveUserAction
{
    public function execute(User $actor, UserData $data, ?string $id): User
    {
        return DB::transaction(function () use ($actor, $data, $id) {
            // Serialize administrator edits to prevent concurrent removal of all administrators.
            $admins = User::role('Super Admin')->where('is_active', true)->whereNull('deleted_at')->orderBy('id')->lockForUpdate()->get();
            $user = $id ? User::whereNull('deleted_at')->lockForUpdate()->findOrFail($id) : new User;
            if ($id === $actor->id && (! $data->active || ! in_array('Super Admin', $data->roles))) {
                throw ValidationException::withMessages(['roles' => 'No puedes desactivar tu cuenta ni retirar tu propio rol Super Admin.']);
            }
            if ($id && $user->hasRole('Super Admin') && (! $data->active || ! in_array('Super Admin', $data->roles)) && $admins->where('id', '!=', $id)->isEmpty()) {
                throw ValidationException::withMessages(['roles' => 'Debe permanecer al menos un Super Admin activo.']);
            }
            $before = $id ? ['branch_id' => $user->branch_id, 'is_active' => (bool) $user->is_active, 'roles' => $user->getRoleNames()->all()] : null;
            $promoter = in_array('Promotor', $data->roles);
            if ((! array_intersect(['Super Admin', 'Gerente General'], $data->roles) || $promoter) && ! $data->branchId) {
                throw ValidationException::withMessages(['branch_id' => 'Este perfil requiere una sucursal activa.']);
            }
            if ($id && Team::where('promoter_id', $id)->exists() && (! $promoter || ! $data->active || $user->branch_id !== $data->branchId)) {
                throw ValidationException::withMessages(['roles' => 'Reasigna primero los grupos del promotor antes de desactivarlo, retirar su rol o cambiar su sucursal.']);
            }
            $user->forceFill(['name' => $data->name, 'email' => $data->email, 'branch_id' => $data->branchId, 'is_active' => $data->active]);
            if ($data->password) {
                if ($id && (\Illuminate\Support\Facades\Hash::check($data->password, $user->password) || ($user->previous_password && \Illuminate\Support\Facades\Hash::check($data->password, $user->previous_password)))) {
                    throw ValidationException::withMessages(['password' => 'Usa una contraseña diferente de la actual y de la anterior.']);
                }
                $user->password = $data->password;
            }
            $user->save();
            $user->syncRoles($data->roles);
            if ($promoter) {
                Promoter::updateOrCreate(['id' => $user->id], ['branch_id' => $data->branchId, 'is_active' => $data->active]);
            } else {
                Promoter::whereKey($user->id)->update(['is_active' => false]);
            }
            // Changes to credentials and access invalidate all existing sessions.
            if ($id) {
                $user->tokens()->delete();
            }
            DB::table('access_audits')->insert(['id' => (string) Str::uuid(), 'actor_id' => $actor->id, 'subject_type' => 'user', 'subject_id' => $user->id, 'action' => $id ? 'updated' : 'created', 'changes' => json_encode(['before' => $before, 'after' => ['branch_id' => $user->branch_id, 'is_active' => (bool) $user->is_active, 'roles' => $data->roles], 'password_changed' => (bool) $data->password]), 'created_at' => now()]);

            return $user->load('roles:id,name');
        });
    }
}
