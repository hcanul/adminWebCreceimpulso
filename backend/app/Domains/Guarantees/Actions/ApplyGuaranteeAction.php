<?php

namespace App\Domains\Guarantees\Actions;

use App\Domains\Credit\Services\CreditAccess;
use App\Domains\Credit\Services\CreditCalculator as Money;
use App\Domains\Credit\Services\CreditCollections;
use App\Domains\Guarantees\Services\GuaranteeBalance;
use App\Models\LoanApplication;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ApplyGuaranteeAction
{
    public function execute(User $actor, string $id, array $input): void
    {
        $access = app(CreditAccess::class);
        $access->authorize($actor, 'guarantee.apply');
        $visible = $access->query($actor)->findOrFail($id);
        DB::transaction(function () use ($actor, $visible, $input) {
            Team::whereKey($visible->team_id)->lockForUpdate()->firstOrFail();
            $loan = LoanApplication::whereKey($visible->id)->lockForUpdate()->firstOrFail();
            $hash = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));
            $existing = DB::table('guarantee_applications')->where('id', $input['id'])->first();
            if ($existing) {
                abort_unless($existing->loan_application_id === $loan->id && $existing->actor_id === $actor->id && hash_equals($existing->request_hash, $hash), 409, 'El identificador ya corresponde a otra operación.');

                return;
            }
            $state = app(GuaranteeBalance::class)->forLoan($loan);
            abort_unless($state['eligible_date'], 422, 'Solo se aplica la garantía a partir del vencimiento de la semana 16.');
            abort_unless(hash_equals($state['version'], $input['version']), 409, 'Los saldos cambiaron. Actualiza antes de aplicar.');
            $rows = app(CreditCollections::class)->rows($loan->id, true);
            $allocations = [];
            $total = 0;
            foreach ($state['members'] as $m) {
                $left = Money::scaled($m['applicable']);
                $total += $left;
                foreach ($rows->where('client_id', $m['client_id']) as $row) {
                    $paid = Money::scaled((string) $row->amount_paid);
                    $take = min($left, Money::scaled((string) $row->amount_due) - $paid);
                    if ($take <= 0) {
                        continue;
                    }$allocations[] = ['id' => (string) Str::uuid(), 'guarantee_application_id' => $input['id'], 'loan_installment_id' => $row->id, 'client_id' => $m['client_id'], 'amount' => Money::money($take)];
                    $row->amount_paid = Money::money($paid + $take);
                    $left -= $take;
                    if ($left === 0) {
                        break;
                    }
                }
            }
            abort_unless($total > 0, 422, 'No hay garantía disponible que aplicar a saldos pendientes.');
            DB::table('guarantee_applications')->insert(['id' => $input['id'], 'loan_application_id' => $loan->id, 'branch_id' => $loan->branch_id, 'actor_id' => $actor->id, 'actor_name' => $actor->name, 'amount' => Money::money($total), 'reason' => $input['reason'], 'request_hash' => $hash, 'applied_on' => now('America/Cancun')->toDateString(), 'created_at' => now()]);
            DB::table('guarantee_allocations')->insert($allocations);
            foreach ($rows as $row) {
                DB::table('loan_installments')->where('id', $row->id)->update(['amount_paid' => $row->amount_paid]);
            }
            if ($rows->every(fn ($r) => Money::scaled((string) $r->amount_due) === Money::scaled((string) $r->amount_paid))) {
                $loan->status = 'pagado';
                $loan->save();
            }
        }, 3);
    }

    public function reverse(User $actor, string $applicationId, string $reason): void
    {
        $access = app(CreditAccess::class);
        $access->authorize($actor, 'guarantee.apply');
        $event = DB::table('guarantee_applications')->where('id', $applicationId)->first();
        abort_unless($event, 404);
        $visible = $access->query($actor)->findOrFail($event->loan_application_id);
        DB::transaction(function () use ($actor, $visible, $event, $reason) {
            Team::whereKey($visible->team_id)->lockForUpdate()->firstOrFail();
            $loan = LoanApplication::whereKey($visible->id)->lockForUpdate()->firstOrFail();
            abort_if(DB::table('guarantee_reversals')->where('guarantee_application_id', $event->id)->exists(), 409, 'Esta aplicación ya fue revertida.');
            $rows = app(CreditCollections::class)->rows($loan->id, true)->keyBy('id');
            $allocations = DB::table('guarantee_allocations')->where('guarantee_application_id', $event->id)->get();
            abort_unless($allocations->sum(fn ($a) => Money::scaled((string) $a->amount)) === Money::scaled((string) $event->amount), 409, 'La aplicación no coincide con su detalle.');
            foreach ($allocations as $a) {
                $row = $rows->get($a->loan_installment_id);
                abort_unless($row, 409);
                $paid = Money::scaled((string) $row->amount_paid) - Money::scaled((string) $a->amount);
                abort_unless($paid >= 0, 409, 'Saldo inconsistente.');
                $row->amount_paid = Money::money($paid);
                DB::table('loan_installments')->where('id', $row->id)->update(['amount_paid' => $row->amount_paid]);
            }
            DB::table('guarantee_reversals')->insert(['id' => (string) Str::uuid(), 'guarantee_application_id' => $event->id, 'actor_id' => $actor->id, 'actor_name' => $actor->name, 'reason' => $reason, 'reversed_on' => now('America/Cancun')->toDateString(), 'created_at' => now()]);
            $loan->status = 'entregado';
            $loan->save();
        }, 3);
    }
}
