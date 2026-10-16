<?php

namespace App\Domains\Credit\Services;

use App\Domains\Intake\Services\IntakeAccess;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class CreditAccess
{
    public function authorize(User $actor, string $permission = 'credit.view'): void
    {
        abort_unless($actor->is_active && ! $actor->deleted_at && ($actor->hasRole('Super Admin') || $actor->can($permission)), 403);
    }

    public function query(User $actor): Builder
    {
        $this->authorize($actor);

        if ((! app(IntakeAccess::class)->allowed($actor) || $actor->can('credit.review') || $actor->can('credit.authorize') || $actor->can('credit.propose') || $actor->can('credit.disburse') || $actor->can('credit.collect') || $actor->can('credit.reverse')) && ! app(IntakeAccess::class)->global($actor)) {
            return LoanApplication::where('branch_id', $actor->branch_id ?? '');
        }

        return LoanApplication::whereIn('team_id', app(IntakeAccess::class)->teams($actor)->select('id'));
    }
}
