<?php

namespace App\Domains\Credit\Services;

use App\Domains\Guarantees\Services\GuaranteeBalance;
use App\Domains\LateFees\Services\LateFeeLedger;
use App\Domains\LateFees\Services\LateFeePreview;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreditStatement
{
    public function payload(User $actor, string $id): array
    {
        $access = app(CreditAccess::class);
        $access->authorize($actor);

        return DB::transaction(function () use ($actor, $id, $access) {
            // Collections and reversals acquire this lock before changing installments.
            // No balance or history query precedes it within this transaction.
            $loan = $access->query($actor)->lockForUpdate()->findOrFail($id);
            abort_unless(in_array($loan->status, ['entregado', 'pagado'], true) && $loan->approved_snapshot, 409, 'El estado de cuenta requiere un crédito entregado con condiciones conservadas.');
            $delivery = DB::table('credit_disbursements')->where('loan_application_id', $id)->first();
            abort_unless($delivery, 409, 'No existe una entrega registrada para este crédito.');
            $collections = app(CreditCollections::class);
            $rows = $collections->rows($id);
            $snapshot = $loan->approved_snapshot;
            $today = now('America/Cancun')->toDateString();
            $balances = $collections->memberBalances($rows, $snapshot['members'], $today)->keyBy('client_id');
            abort_unless($rows->count() === count($snapshot['members']) * 16, 409, 'El calendario está incompleto. Solicita revisión antes de emitir el estado de cuenta.');
            $expected = collect(app(CreditDisbursement::class)->schedule($snapshot, $delivery->first_due_on))
                ->keyBy(fn ($r) => $r['client_id'].':'.$r['number']);
            foreach ($rows as $row) {
                $reference = $expected[$row->client_id.':'.$row->number] ?? null;
                abort_unless($reference && $reference['due_on'] === $row->due_on
                    && $reference['amount_due'] === CreditCalculator::money(CreditCalculator::scaled((string) $row->amount_due)), 409, 'El calendario no coincide con la entrega registrada.');
            }
            $members = collect($snapshot['members'])->map(function ($member) use ($rows, $balances) {
                $schedule = $rows->where('client_id', $member['client_id']);
                abort_unless($schedule->count() === 16 && $schedule->pluck('number')->unique()->count() === 16, 409, 'El calendario individual está incompleto.');
                $total = $paid = 0;
                foreach ($schedule as $row) {
                    $amount = CreditCalculator::scaled((string) $row->amount_due);
                    $applied = CreditCalculator::scaled((string) $row->amount_paid);
                    abort_unless($applied <= $amount, 409, 'Revisa la integridad de los saldos antes de emitir el estado de cuenta.');
                    $total += $amount;
                    $paid += $applied;
                }
                abort_unless(CreditCalculator::money($total) === $member['estimate']['total_repayment'], 409, 'El calendario no coincide con las condiciones aprobadas.');

                return $balances[$member['client_id']] + [
                    'role_in_group' => $member['role_in_group'],
                    'principal' => $member['amount_approved'],
                    'interest' => $member['estimate']['interest'],
                    'weekly_payment' => $member['estimate']['weekly_payment'],
                    'guarantee' => $member['estimate']['guarantee'],
                    'net_delivery' => $member['estimate']['net_delivery'],
                    'total_repayment' => CreditCalculator::money($total),
                    'paid' => CreditCalculator::money($paid),
                ];
            })->values();
            $payments = DB::table('credit_payments')->where('loan_application_id', $id)->orderBy('number')
                ->get(['id', 'number', 'received_on', 'method', 'amount']);
            $allocations = DB::table('credit_payment_allocations as a')
                ->join('loan_installments as i', 'i.id', '=', 'a.loan_installment_id')
                ->whereIn('a.credit_payment_id', $payments->pluck('id'))
                ->orderBy('i.number')->orderBy('a.id')
                ->get(['a.credit_payment_id', 'a.payer_client_id', 'a.amount', 'i.client_id', 'i.number', 'i.due_on'])
                ->concat(app(LateFeeLedger::class)->allocations($payments->pluck('id')->all()))->groupBy('credit_payment_id');
            // Operational notes, bank references and reversal reasons stay in their restricted modules.
            $reversals = DB::table('credit_payment_reversals')->whereIn('credit_payment_id', $payments->pluck('id'))
                ->get(['credit_payment_id', 'reversed_on'])->keyBy('credit_payment_id');
            $validPaid = 0;
            $payments = $payments->map(function ($payment) use ($loan, $allocations, $reversals, &$validPaid) {
                $payment->amount = CreditCalculator::money(CreditCalculator::scaled((string) $payment->amount));
                $payment->folio = 'REC-'.$loan->folio.'-'.str_pad((string) $payment->number, 4, '0', STR_PAD_LEFT);
                $payment->reversed_on = $reversals[$payment->id]->reversed_on ?? null;
                if (! $payment->reversed_on) {
                    $validPaid += CreditCalculator::scaled($payment->amount) - ($allocations[$payment->id] ?? collect())->filter(fn ($a) => ($a->kind ?? '') === 'late_fee')->sum(fn ($a) => CreditCalculator::scaled((string) $a->amount));
                }
                $payment->allocations = ($allocations[$payment->id] ?? collect())->map(function ($allocation) {
                    unset($allocation->credit_payment_id);
                    $allocation->amount = CreditCalculator::money(CreditCalculator::scaled((string) $allocation->amount));

                    return $allocation;
                });
                abort_unless($payment->allocations->sum(fn ($a) => CreditCalculator::scaled($a->amount)) === CreditCalculator::scaled($payment->amount), 409, 'El detalle de un pago está incompleto. Solicita revisión.');

                return $payment;
            });
            $guaranteeState = app(GuaranteeBalance::class)->forLoan($loan);
            $guarantees = $guaranteeState['history']->map(function ($event) use (&$validPaid) {
                if (! $event->reversal) {
                    $validPaid += CreditCalculator::scaled($event->amount);
                }

                return ['id' => $event->id, 'amount' => $event->amount, 'applied_on' => $event->applied_on, 'reversed_on' => $event->reversal?->reversed_on, 'allocations' => collect($event->allocations)->map(fn ($a) => ['client_id' => $a->client_id, 'amount' => CreditCalculator::money(CreditCalculator::scaled((string) $a->amount)), 'number' => $a->number, 'due_on' => $a->due_on])];
            });
            abort_unless($validPaid === $members->sum(fn ($m) => CreditCalculator::scaled($m['paid'])), 409, 'Los pagos y aplicaciones de garantía vigentes no coinciden con los saldos. Solicita revisión.');

            $fees = app(LateFeeLedger::class)->state($loan, $rows);
            $members = app(LateFeeLedger::class)->members($members, $fees);

            return [
                'late_fees' => $fees,
                'credit' => [
                    'id' => $loan->id, 'folio' => $loan->folio, 'status' => $loan->status,
                    'group' => $loan->team?->name, 'branch' => $loan->branch?->name,
                    'disbursed_on' => $delivery->disbursed_on, 'first_due_on' => $delivery->first_due_on,
                    'repayment_method' => $loan->repayment_method,
                    'rate_name' => $snapshot['rate']['name'], 'rate_factor' => $snapshot['rate']['factor'],
                ],
                'as_of' => $today, 'generated_at_local' => now('America/Cancun')->format('Y-m-d H:i:s'),
                'members' => $members,
                'installments' => $rows->map(fn ($r) => [
                    'client_id' => $r->client_id, 'number' => $r->number, 'due_on' => $r->due_on,
                    'amount_due' => CreditCalculator::money(CreditCalculator::scaled((string) $r->amount_due)),
                    'amount_paid' => CreditCalculator::money(CreditCalculator::scaled((string) $r->amount_paid)),
                ]),
                'payments' => $payments, 'guarantees' => $guarantees,
                'late_fee_preview' => $fees['enabled'] ? null : app(LateFeePreview::class)->forLoan($id, $rows, $today),
            ];
        }, 3);
    }
}
