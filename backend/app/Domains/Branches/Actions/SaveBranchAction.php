<?php

namespace App\Domains\Branches\Actions;

use App\Domains\Branches\DTOs\BranchData;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SaveBranchAction
{
    public function execute(User $actor, BranchData $data, ?string $id): Branch
    {
        return DB::transaction(function () use ($actor, $data, $id) {
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->is_active && ! $actor->deleted_at && $actor->hasAnyRole(['Super Admin', 'Gerente General']), 403);
            $fields = $data->fields();
            $branch = Branch::withTrashed()->whereKey($id ?? $data->values['id'])->lockForUpdate()->first();
            if (! $id && $branch) {
                abort_unless(! $branch->deleted_at && hash_equals($branch->creation_hash ?? '', $data->fingerprint($actor->id)), 409, 'El identificador ya pertenece a otra sucursal.');

                return $branch;
            }
            if ($id) {
                abort_unless($branch && ! $branch->deleted_at, 404);
                if ($branch->management_version !== $data->values['version']) {
                    $same = (int) $branch->management_version === $data->values['version'] + 1;
                    foreach ($fields as $key => $value) {
                        $same = $same && $value === $branch->$key;
                    }
                    abort_unless($same, 409, 'La sucursal cambió. Actualiza la lista antes de editar.');

                    return $branch;
                }
            } else {
                $branch = new Branch;
                $branch->id = $data->values['id'];
            }
            if (! $fields['is_active']) {
                abort_if(DB::table('loan_applications')->where('branch_id', $branch->id)->whereNull('deleted_at')->whereIn('status', ['pendiente', 'aprobado', 'entregado'])->exists(), 422, 'No se puede desactivar una sucursal con solicitudes o créditos en operación.');
                abort_if(User::where('branch_id', $branch->id)->where('is_active', true)->whereNull('deleted_at')->exists(), 422, 'Reasigna o desactiva primero los usuarios activos de la sucursal.');
            }
            $before = $id ? $branch->only(array_keys($fields)) : null;
            $branch->forceFill($fields);
            $branch->management_version = $id ? $data->values['version'] + 1 : 1;
            if (! $id) {
                $branch->creation_hash = $data->fingerprint($actor->id);
            }
            $branch->save();
            DB::table('access_audits')->insert(['id' => (string) Str::uuid(), 'actor_id' => $actor->id, 'subject_type' => 'branch', 'subject_id' => $branch->id, 'action' => $id ? 'updated' : 'created', 'changes' => json_encode(['before' => $before, 'after' => $branch->only(array_keys($fields))], JSON_THROW_ON_ERROR), 'created_at' => now()]);

            return $branch;
        }, 3);
    }
}
