<?php

namespace App\Domains\Portfolio\Actions;

use App\Domains\Credit\Services\CreditAccess;
use App\Domains\Credit\Services\CreditCalculator as Money;
use App\Domains\LateFees\Services\LateFeeLedger;
use App\Domains\Portfolio\DTOs\PortfolioCommand;
use App\Domains\Portfolio\Enums\PortfolioClass;
use App\Models\PortfolioEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ReclassifyPortfolioAction
{
    public function execute(User $actor, string $loanId, PortfolioCommand $data): void
    {
        DB::transaction(function () use ($actor, $loanId, $data) {
            // A permission assigned to a local manager never bypasses the confirmed role rule.
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            app(CreditAccess::class)->authorize($actor, 'credit.portfolio.manage');
            abort_unless($actor->hasAnyRole(['Super Admin', 'Gerente General']), 403);
            $loan = app(CreditAccess::class)->query($actor)->whereKey($loanId)->lockForUpdate()->firstOrFail();
            $v = $data->values;
            $existing = PortfolioEvent::find($v['id']);
            if ($existing) {
                abort_unless($existing->actor_id === $actor->id && hash_equals($existing->request_hash, $data->fingerprint($loanId)), 409, 'La operación ya corresponde a otro contenido.');

                return;
            }
            abort_unless($loan->status === 'entregado', 409, 'Solo se reclasifican créditos entregados con seguimiento vigente.');
            abort_unless((int) $loan->portfolio_version === $v['version'], 409, 'La clasificación cambió. Actualiza antes de continuar.');
            $target = PortfolioClass::from($v['classification']);
            abort_if($loan->portfolio_class === $target->value, 409, 'El crédito ya tiene esa clasificación.');
            if ($target === PortfolioClass::Recovery) {
                $late = DB::table('loan_installments')->where('loan_application_id', $loan->id)->where('due_on', '<', now('America/Cancun')->toDateString())->whereColumn('amount_due', '>', 'amount_paid')->exists();
                $state = app(LateFeeLedger::class)->statesFor(collect([$loan]))->get($loan->id);
                abort_unless($late || Money::scaled($state['balance'] ?? '0.00') > 0, 422, 'El crédito no tiene atraso para trasladarlo a recuperación.');
            }
            PortfolioEvent::create(['id' => $v['id'], 'loan_application_id' => $loan->id, 'branch_id' => $loan->branch_id,
                'actor_id' => $actor->id, 'actor_name' => $actor->name, 'previous_class' => $loan->portfolio_class,
                'next_class' => $target->value, 'version' => $v['version'] + 1, 'reason' => $v['reason'],
                'request_hash' => $data->fingerprint($loanId), 'created_at' => now()]);
            $loan->forceFill(['portfolio_class' => $target->value, 'portfolio_version' => $v['version'] + 1])->save();
        }, 3);
    }
}
