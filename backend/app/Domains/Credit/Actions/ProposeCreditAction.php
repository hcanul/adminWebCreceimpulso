<?php

namespace App\Domains\Credit\Actions;

use App\Domains\Credit\DTOs\ProposeCreditData;
use App\Domains\Credit\Enums\AuthorizationStatus;
use App\Domains\Credit\Services\CreditAccess;
use App\Domains\Credit\Services\CreditAuthorization;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ProposeCreditAction
{
    public function execute(User $actor, string $id, ProposeCreditData $data): LoanApplication
    {
        $access = app(CreditAccess::class);
        $access->authorize($actor, 'credit.propose');
        $visible = $access->query($actor)->findOrFail($id);

        return DB::transaction(function () use ($actor, $visible, $data) {
            $service = app(CreditAuthorization::class);
            $loan = $service->lock($visible, $data->version);
            $before = $loan->authorization_status;
            abort_unless(in_array($before, [AuthorizationStatus::AwaitingProposal->value, AuthorizationStatus::Returned->value]), 409, 'La propuesta enviada está cerrada; Coordinación debe devolverla antes de cambiarla.');
            $service->validateEligibility($loan);
            $proposal = $service->calculate($loan, $data);
            $proposal['proposed_by'] = $actor->id;
            $proposal['proposed_by_name'] = $actor->name;
            $proposal['proposed_at'] = now()->toISOString();
            $loan->forceFill(['authorization_proposal' => $proposal, 'authorization_status' => AuthorizationStatus::Pending->value, 'authorization_version' => $loan->authorization_version + 1])->save();
            $service->record($actor, $loan, 'propose', $before, $data->notes);

            return $loan;
        }, 3);
    }
}
