<?php

namespace App\Domains\Credit\Services;

use App\Models\ClientApplication;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreditReview
{
    public const CHECKS = ['identity', 'address', 'references', 'visit', 'internal_history', 'signed_documents'];

    public function files(LoanApplication $loan, bool $lock = false)
    {
        $query = ClientApplication::where('team_id', $loan->team_id)->where('branch_id', $loan->branch_id)
            ->whereIn('client_id', array_column($loan->request_snapshot['members'] ?? [], 'client_id'))
            ->orderByDesc('created_at')->orderByDesc('id');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get()->unique('client_id')->values();
    }

    public function payload(User $actor, LoanApplication $loan): array
    {
        app(CreditAccess::class)->authorize($actor);
        abort_unless($actor->hasRole('Super Admin') || $actor->can('credit.review') || $actor->can('credit.create') || $actor->can('credit.authorize'), 403);

        return [
            'status' => $loan->review_status, 'version' => $loan->review_version,
            'checks' => $loan->review_checks ?? [], 'files' => $this->files($loan),
            'history' => DB::table('credit_review_events')->where('loan_application_id', $loan->id)->orderByDesc('version')->get()->map(function ($event) {
                $event->checks = json_decode($event->checks, true);
                $event->files = json_decode($event->files, true);

                return $event;
            }),
        ];
    }
}
