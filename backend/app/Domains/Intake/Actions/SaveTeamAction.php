<?php

namespace App\Domains\Intake\Actions;

use App\Domains\Intake\DTOs\SaveTeamData;
use App\Domains\Intake\Services\IntakeAccess;
use App\Models\Branch;
use App\Models\Promoter;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SaveTeamAction
{
    public function execute(User $actor, SaveTeamData $dto, ?Team $team = null): Team
    {
        $data = $dto->toArray();
        $access = app(IntakeAccess::class);
        abort_unless($access->allowed($actor), 403);
        abort_unless($access->global($actor) || $data['branch_id'] === $actor->branch_id, 403);
        abort_unless(Branch::whereKey($data['branch_id'])->where('is_active', true)->exists(), 422, 'Sucursal no disponible.');

        return DB::transaction(function () use ($team, $data, $actor, $access) {
            // Share the user lock with account edits so eligibility cannot change during assignment.
            User::whereKey($data['promoter_id'])->lockForUpdate()->firstOrFail();
            $promoter = Promoter::assignable()->whereKey($data['promoter_id'])->first();
            abort_unless($promoter, 422, 'Selecciona un promotor registrado, activo y con rol Promotor.');
            abort_unless($promoter->branch_id === $data['branch_id'], 422, 'El promotor no pertenece a la sucursal.');
            abort_if($access->field($actor) && $actor->hasRole('Promotor') && $promoter->id !== $actor->id, 403);
            if ($team) {
                $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();
                abort_if($team->branch_id !== $data['branch_id'], 422, 'No se puede trasladar un grupo a otra sucursal desde edición.');
                $team->update(['name' => $data['name'], 'promoter_id' => $data['promoter_id']]);
            } else {
                $team = Team::create($data + ['created_by' => $actor->id]);
            }

            return $team->load(['branch:id,name', 'promoter:id,branch_id'])->loadCount('memberships');
        });
    }
}
