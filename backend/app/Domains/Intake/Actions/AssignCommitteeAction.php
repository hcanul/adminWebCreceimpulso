<?php

namespace App\Domains\Intake\Actions;

use App\Models\Team;
use App\Models\TeamMembership;
use Illuminate\Support\Facades\DB;

final class AssignCommitteeAction
{
    public function execute(Team $team, string $membershipId, string $role): TeamMembership
    {
        return DB::transaction(function () use ($team, $membershipId, $role) {
            Team::whereKey($team->id)->lockForUpdate()->firstOrFail();
            $member = TeamMembership::where('team_id', $team->id)->whereKey($membershipId)->firstOrFail();
            abort_if($role !== 'integrante' && TeamMembership::where('team_id', $team->id)->where('id', '!=', $member->id)->where('role_in_group', $role)->exists(), 422, 'Ese cargo ya está asignado. Libéralo antes de reasignarlo.');
            $member->update(['role_in_group' => $role]);

            return $member;
        });
    }
}
