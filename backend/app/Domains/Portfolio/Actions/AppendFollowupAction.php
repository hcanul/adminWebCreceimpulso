<?php

namespace App\Domains\Portfolio\Actions;

use App\Domains\Credit\Services\CreditAccess;
use App\Domains\Portfolio\DTOs\PortfolioCommand;
use App\Models\CreditFollowupEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class AppendFollowupAction
{
    public function execute(User $actor, string $loanId, PortfolioCommand $data): void
    {
        DB::transaction(function () use ($actor, $loanId, $data) {
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            app(CreditAccess::class)->authorize($actor, 'credit.followup');
            $loan = app(CreditAccess::class)->query($actor)->whereKey($loanId)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($loan->status, ['entregado', 'pagado'], true), 409, 'El seguimiento de cobranza requiere un crédito entregado.');
            $v = $data->values;
            $existing = CreditFollowupEntry::find($v['id']);
            if ($existing) {
                abort_unless($existing->actor_id === $actor->id && hash_equals($existing->request_hash, $data->fingerprint($loanId)), 409, 'La operación ya corresponde a otra observación.');

                return;
            }
            $member = $loan->participations()->where('client_id', $v['client_id'])->firstOrFail();
            $sequence = (int) CreditFollowupEntry::where('loan_application_id', $loanId)->max('sequence') + 1;
            CreditFollowupEntry::create(['id' => $v['id'], 'loan_application_id' => $loanId, 'branch_id' => $loan->branch_id,
                'loan_participation_id' => $member->id, 'client_id' => $member->client_id,
                'actor_id' => $actor->id, 'actor_name' => $actor->name, 'sequence' => $sequence,
                'observation' => $v['observation'], 'created_at' => now(), 'request_hash' => $data->fingerprint($loanId)]);
        }, 3);
    }
}
