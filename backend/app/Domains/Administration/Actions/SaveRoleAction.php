<?php

namespace App\Domains\Administration\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class SaveRoleAction
{
    public function execute(User $actor, array $data, ?string $id): Role
    {
        $role = DB::transaction(function () use ($actor, $data, $id) {
            $role = $id ? Role::where('guard_name', 'web')->lockForUpdate()->findOrFail($id) : Role::create(['name' => $data['name'], 'guard_name' => 'web']);
            abort_if($role->name === 'Super Admin', 422, 'El rol Super Admin está protegido.');
            // Existing names define operational scope and cannot be renamed.
            if (array_diff($data['permissions'], ['messages.send', 'intake.access', 'credit.view', 'credit.disburse', 'credit.collect', 'credit.reverse', 'cash.view', 'cash.close', 'cash.audit']) && ! in_array('intake.access', $data['permissions'])) {
                throw ValidationException::withMessages(['permissions' => 'Habilita el acceso a grupos y expedientes para estos permisos.']);
            }
            if (array_intersect($data['permissions'], ['credit.create', 'credit.rates', 'credit.review', 'credit.propose', 'credit.authorize', 'credit.disburse', 'credit.collect', 'credit.reverse']) && ! in_array('credit.view', $data['permissions'])) {
                throw ValidationException::withMessages(['permissions' => 'Habilita también la consulta de solicitudes de crédito.']);
            }
            if (array_intersect($data['permissions'], ['cash.close', 'cash.audit']) && ! in_array('cash.view', $data['permissions'])) {
                throw ValidationException::withMessages(['permissions' => 'Habilita también la consulta de cortes de caja.']);
            }
            $before = $role->permissions()->pluck('name')->all();
            $role->syncPermissions($data['permissions']);
            DB::table('access_audits')->insert(['id' => (string) Str::uuid(), 'actor_id' => $actor->id, 'subject_type' => 'role', 'subject_id' => (string) $role->id, 'action' => $id ? 'updated' : 'created', 'changes' => json_encode(['before' => $before, 'after' => $data['permissions']]), 'created_at' => now()]);

            return $role->load('permissions:id,name');
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role;
    }
}
