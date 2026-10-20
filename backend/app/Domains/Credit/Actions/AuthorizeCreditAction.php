<?php

namespace App\Domains\Credit\Actions;

use App\Domains\Credit\DTOs\AuthorizeCreditData;
use App\Domains\Credit\Enums\AuthorizationStatus;
use App\Domains\Credit\Services\CreditAccess;
use App\Domains\Credit\Services\CreditAuthorization;
use App\Domains\LateFees\Services\LateFeeLedger;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class AuthorizeCreditAction
{
    public function execute(User $actor, string $id, AuthorizeCreditData $data): LoanApplication
    {
        $access = app(CreditAccess::class);
        $access->authorize($actor, 'credit.authorize');
        $visible = $access->query($actor)->findOrFail($id);

        return DB::transaction(function () use ($actor, $visible, $data) {
            $service = app(CreditAuthorization::class);
            $loan = $service->lock($visible, $data->version);
            $before = $loan->authorization_status;
            abort_unless($before === AuthorizationStatus::Pending->value && $loan->authorization_proposal, 409, 'Se requiere una propuesta enviada por el gerente.');
            $proposal = $loan->authorization_proposal;
            if ($data->decision === 'approve') {
                abort_unless($proposal['review_version'] === $loan->review_version, 409, 'La revisión de Gestión cambió.');
                $service->validateEligibility($loan);
                $participations = $loan->participations()->orderBy('client_id')->lockForUpdate()->get()->keyBy('client_id');
                abort_unless($participations->count() === count($proposal['members']), 409, 'Las participaciones cambiaron.');
                foreach ($proposal['members'] as $row) {
                    $participation = $participations->get($row['client_id']);
                    abort_unless($participation && $participation->amount_requested === $row['amount_requested'] && $participation->role_in_group === $row['role_in_group'], 409, 'Las participaciones cambiaron.');
                    $participation->update(['amount_approved' => $row['amount_proposed']]);
                }
                $approved = $proposal;
                $approved['first_due_on'] = $data->firstDueOn;
                if (config('late_fees.new_authorizations')) {
                    $approved['late_fee_policy'] = LateFeeLedger::policy();
                }
                $approved['members'] = array_map(function ($member) {
                    $member['amount_approved'] = $member['amount_proposed'];
                    unset($member['amount_proposed']);

                    return $member;
                }, $proposal['members']);
                $loan->forceFill(['status' => 'aprobado', 'total_approved' => $proposal['totals']['principal'], 'approved_snapshot' => $approved, 'approved_by' => $actor->id, 'approved_at' => now(), 'disbursement_method' => $proposal['disbursement_method'], 'repayment_method' => $proposal['repayment_method']]);
            }
            $loan->forceFill(['authorization_status' => $data->decision === 'approve' ? AuthorizationStatus::Authorized->value : AuthorizationStatus::Returned->value, 'authorization_version' => $loan->authorization_version + 1])->save();
            $service->record($actor, $loan, $data->decision, $before, $data->notes);

            return $loan;
        }, 3);
    }
}
