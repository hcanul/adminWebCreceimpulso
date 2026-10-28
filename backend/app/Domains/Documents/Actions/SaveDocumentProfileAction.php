<?php

namespace App\Domains\Documents\Actions;

use App\Domains\Documents\Services\DocumentProfiles;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SaveDocumentProfileAction
{
    public function execute(User $actor, string $scope, array $data): array
    {
        abort_unless($actor->is_active && ! $actor->deleted_at && $actor->hasRole('Super Admin'), 403);

        return DB::transaction(function () use ($actor, $scope, $data) {
            if ($scope !== 'general') {
                Branch::whereKey($scope)->lockForUpdate()->firstOrFail();
                DB::table('document_profiles')->insertOrIgnore(['scope' => $scope, 'fields' => '{}']);
            }
            DB::table('document_profiles')->where('scope', $scope)->lockForUpdate()->firstOrFail();
            $profiles = app(DocumentProfiles::class);
            $before = $profiles->read($scope);
            abort_unless($before['version'] === $data['version'], 409, 'Otro usuario modificó estos datos. Recarga antes de guardar.');
            $fields = array_map(fn ($value) => trim($value ?? ''), $data['fields']);
            DB::table('document_profiles')->where('scope', $scope)->update(['fields' => json_encode($fields, JSON_THROW_ON_ERROR), 'use_general' => $scope === 'general' || $data['use_general'], 'version' => $before['version'] + 1, 'updated_by' => $actor->id, 'updated_at' => now()]);
            $after = $profiles->read($scope);
            DB::table('access_audits')->insert(['id' => (string) Str::uuid(), 'actor_id' => $actor->id, 'subject_type' => 'document_profile', 'subject_id' => $scope, 'action' => 'update', 'changes' => json_encode(['before' => $before, 'after' => $after], JSON_THROW_ON_ERROR), 'created_at' => now()]);

            return $after;
        }, 3);
    }
}
