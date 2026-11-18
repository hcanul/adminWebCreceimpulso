<?php

namespace App\Domains\Intake\Actions;

use App\Domains\Intake\DTOs\SaveTeamData;
use App\Domains\Intake\Services\IntakeAccess;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateOfflineTeamAction
{
    public function execute(User $actor, string $id, SaveTeamData $dto): Team
    {
        $hash = hash('sha256', json_encode($dto->toArray(), JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $id, $dto, $hash) {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $access = app(IntakeAccess::class);
            abort_unless($access->allowed($actor), 403);
            $prior = DB::table('offline_group_creations')->where('id', $id)->first();
            if ($prior) {
                abort_unless($prior->actor_id === $actor->id && hash_equals($prior->request_hash, $hash), 409, 'El identificador pertenece a otra operación.');

                return $access->team($actor, $id)->load(['branch:id,name', 'promoter:id,branch_id']);
            }
            abort_if(Team::withTrashed()->whereKey($id)->exists(), 409, 'Identificador de grupo ya registrado.');
            $team = app(SaveTeamAction::class)->execute($actor, $dto, null, $id);
            DB::table('offline_group_creations')->insert(['id' => $id, 'actor_id' => $actor->id, 'request_hash' => $hash, 'created_at' => now()]);

            return $team;
        }, 3);
    }
}
