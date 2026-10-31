<?php

namespace App\Domains\Credit\Actions;

use App\Domains\Credit\DTOs\ReversePaymentData;
use App\Domains\Credit\Services\CreditAccess;
use App\Domains\Credit\Services\CreditCalculator as Money;
use App\Domains\Credit\Services\CreditCollections;
use App\Domains\LateFees\Services\LateFeeLedger;
use App\Models\LoanApplication;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class ReversePaymentAction
{
    public function execute(User $actor, string $paymentId, ReversePaymentData $data): void
    {
        $access = app(CreditAccess::class);
        $access->authorize($actor, 'credit.reverse');
        $payment = DB::table('credit_payments')->where('id', $paymentId)->first();
        abort_unless($payment, 404);
        $visible = $access->query($actor)->findOrFail($payment->loan_application_id);
        try {
            DB::transaction(function () use ($actor, $payment, $visible, $data) {
                // Serialize against cuts by the original cashier, then against all changes to this loan.
                DB::table('users')->where('id', $payment->actor_id)->lockForUpdate()->first();
                Team::whereKey($visible->team_id)->lockForUpdate()->firstOrFail();
                $loan = LoanApplication::whereKey($visible->id)->lockForUpdate()->firstOrFail();
                $existing = DB::table('credit_payment_reversals')->where('id', $data->id)->first();
                if ($existing) {
                    abort_unless($existing->credit_payment_id === $payment->id && $existing->actor_id === $actor->id && hash_equals($existing->request_hash, $data->fingerprint()), 409, 'El identificador corresponde a otro reverso.');

                    return;
                }
                abort_if(DB::table('credit_payment_reversals')->where('credit_payment_id', $payment->id)->exists(), 409, 'Este pago ya fue revertido.');
                abort_unless(in_array($loan->status, ['entregado', 'pagado'], true), 409, 'El crédito no admite este reverso en su estado actual.');
                $service = app(CreditCollections::class);
                $rows = $service->rows($loan->id, true);
                abort_unless(hash_equals($service->version($rows), $data->version), 409, 'El saldo cambió. Actualiza el comprobante antes de revertir.');
                app(LateFeeLedger::class)->state($loan, $rows, true);
                $feeAllocations = app(LateFeeLedger::class)->allocations([$payment->id]);
                $allocations = DB::table('credit_payment_allocations')->where('credit_payment_id', $payment->id)->orderBy('id')->get();
                $total = $feeAllocations->sum(fn ($a) => Money::scaled((string) $a->amount));
                $changes = [];
                foreach ($allocations->groupBy('loan_installment_id') as $installmentId => $parts) {
                    $row = $rows->firstWhere('id', $installmentId);
                    abort_unless($row, 409, 'La aplicación del pago no coincide con el calendario.');
                    $amount = $parts->sum(fn ($p) => Money::scaled((string) $p->amount));
                    $paid = Money::scaled((string) $row->amount_paid);
                    abort_unless($amount > 0 && $paid >= $amount, 409, 'El saldo de una cuota requiere revisión antes del reverso.');
                    $total += $amount;
                    $changes[] = ['id' => $row->id, 'before' => Money::money($paid), 'after' => Money::money($paid - $amount)];
                }
                abort_unless($total === Money::scaled((string) $payment->amount), 409, 'El detalle aplicado no coincide con el importe del pago.');
                $closing = DB::table('cash_closing_payments')->where('credit_payment_id', $payment->id)->value('cash_closing_id');
                DB::table('credit_payment_reversals')->insert(['id' => $data->id, 'credit_payment_id' => $payment->id, 'branch_id' => $payment->branch_id, 'actor_id' => $actor->id, 'actor_name' => $actor->name, 'reason' => $data->reason, 'amount' => $payment->amount, 'method' => $payment->method, 'reversed_on' => now('America/Cancun')->toDateString(), 'created_at' => now(), 'request_hash' => $data->fingerprint(), 'snapshot' => json_encode(['loan_status_before' => $loan->status, 'cash_closing_id' => $closing, 'allocations' => $allocations, 'late_fee_allocations' => $feeAllocations, 'installments' => $changes], JSON_THROW_ON_ERROR)]);
                foreach ($changes as $change) {
                    DB::table('loan_installments')->where('id', $change['id'])->update(['amount_paid' => $change['after']]);
                }
                DB::table('active_bank_payment_references')->where('credit_payment_id', $payment->id)->delete();
                DB::table('app_messages')->where('payment_id', $payment->id)->where('kind', 'payment_received')
                    ->whereNull('cancelled_at')->update(['cancelled_at' => now(), 'updated_at' => now()]);
                if ($loan->status === 'pagado') {
                    $loan->status = 'entregado';
                    $loan->save();
                }
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            abort(409, 'El reverso ya se registró. Actualiza antes de reintentar.');
        }
    }
}
