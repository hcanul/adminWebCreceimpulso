<?php

namespace App\Domains\Documents\Services;

use App\Domains\Credit\Services\CreditAccess;
use App\Domains\Intake\Services\IntakeAccess;
use App\Models\ClientApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DocumentAccess
{
    public function source(User $actor, string $type, string $id, bool $lock = false)
    {
        abort_unless($actor->is_active && ! $actor->deleted_at, 403);
        if ($type === 'credit') {
            $q = app(CreditAccess::class)->query($actor);
        } else {
            abort_unless($type === 'file', 404);
            $intake = app(IntakeAccess::class);
            $q = ClientApplication::query();
            if ($intake->allowed($actor)) {
                $q->whereIn('team_id', $intake->teams($actor)->select('id'));
            } else {
                abort_unless($actor->can('credit.review') || $actor->can('credit.authorize'), 403);
                $q->whereIn('team_id', app(CreditAccess::class)->query($actor)->select('team_id'));
                if (! $intake->global($actor)) $q->where('branch_id', $actor->branch_id ?? '');
            }
        }
        if ($lock) $q->lockForUpdate();
        return $q->findOrFail($id);
    }

    public function document(User $actor, string $id)
    {
        $row = DB::table('document_versions')->where('id', $id)->first();
        abort_unless($row, 404);
        $this->source($actor, $row->source_type, $row->source_id);
        return $row;
    }
}
