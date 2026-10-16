<?php

namespace App\Domains\Credit\Actions;

use App\Domains\Credit\DTOs\CollectCreditData;
use App\Domains\Credit\Services\CreditAccess;
use App\Domains\Credit\Services\CreditCalculator as Money;
use App\Domains\Credit\Services\CreditCollections;
use App\Models\LoanApplication;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CollectCreditAction
{
    public function execute(User $actor, string $id, CollectCreditData $data): LoanApplication
    {
        $access = app(CreditAccess::class);
        $access->authorize($actor, 'credit.collect');
        $visible = $access->query($actor)->findOrFail($id);
        try {
            return DB::transaction(function () use ($actor, $visible, $data) {
                User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                Team::whereKey($visible->team_id)->lockForUpdate()->firstOrFail();
                $loan = LoanApplication::whereKey($visible->id)->lockForUpdate()->firstOrFail();
                $v = $data->values;
                $existing = DB::table('credit_payments')->where('id', $v['id'])->first();
                if ($existing) {
                    abort_unless($existing->loan_application_id === $loan->id && $existing->actor_id === $actor->id && hash_equals($existing->request_hash, $data->fingerprint()), 409, 'El identificador corresponde a otro cobro.');

                    return $loan;
                }
                abort_unless($loan->status === 'entregado' && DB::table('credit_disbursements')->where('loan_application_id', $loan->id)->exists(), 409, 'Solo se cobran créditos entregados con saldo pendiente.');
                if ($loan->repayment_method !== $v['method']) {
                    $this->invalid('method', 'Usa la modalidad de pago autorizada para este crédito.');
                }
                $bank = $v['method'] === 'bank_deposit';
                if ($bank && (! $v['bank_name'] || ! $v['bank_reference'] || ! $v['deposited_on'])) {
                    $this->invalid('bank_reference', 'Captura banco, folio del ticket y fecha del depósito.');
                }
                if (! $bank && ($v['bank_name'] || $v['bank_reference'] || $v['deposited_on'])) {
                    $this->invalid('bank_reference', 'Un cobro en efectivo no lleva datos de depósito.');
                }
                $delivery = DB::table('credit_disbursements')->where('loan_application_id', $loan->id)->first();
                if ($bank && $v['deposited_on'] < $delivery->disbursed_on) {
                    $this->invalid('deposited_on', 'El depósito no puede ser anterior a la entrega del crédito.');
                }
                // Scope bank references by branch, bank and deposit date; never treat a folio as globally unique.
                $depositKey = $bank ? hash('sha256', json_encode([$loan->branch_id, mb_strtoupper($v['bank_name']), mb_strtoupper($v['bank_reference']), $v['deposited_on']], JSON_THROW_ON_ERROR)) : null;
                abort_if($depositKey && DB::table('active_bank_payment_references')->where('deposit_key', $depositKey)->exists(), 409, 'Ese ticket bancario ya se registró en la sucursal para ese banco y fecha.');
                $service = app(CreditCollections::class);
                $rows = $service->rows($loan->id, true);
                abort_unless($rows->isNotEmpty() && hash_equals($service->version($rows), $v['balance_version']), 409, 'El saldo cambió. Actualiza y revisa la distribución antes de cobrar.');
                $members = $loan->participations()->pluck('client_id')->all();
                $allocations = [];
                $total = 0;
                foreach ($v['contributions'] as $contribution) {
                    if (! in_array($contribution['client_id'], $members, true) || ! in_array($contribution['payer_client_id'], $members, true)) {
                        $this->invalid('contributions', 'Quien aporta y quien recibe la aplicación deben pertenecer a este crédito.');
                    }
                    $left = Money::scaled($contribution['amount']);
                    if ($left <= 0) {
                        $this->invalid('contributions', 'Cada aportación debe ser mayor que cero.');
                    }
                    $total += $left;
                    foreach ($rows->where('client_id', $contribution['client_id']) as $row) {
                        $paid = Money::scaled((string) $row->amount_paid);
                        $take = min($left, Money::scaled((string) $row->amount_due) - $paid);
                        if ($take <= 0) {
                            continue;
                        }
                        $allocations[] = ['id' => (string) Str::uuid(), 'credit_payment_id' => $v['id'], 'loan_installment_id' => $row->id, 'payer_client_id' => $contribution['payer_client_id'], 'amount' => Money::money($take)];
                        $row->amount_paid = Money::money($paid + $take);
                        $left -= $take;
                        if ($left === 0) {
                            break;
                        }
                    }
                    if ($left > 0) {
                        $this->invalid('contributions', 'La aportación supera el saldo de la integrante. Revisa la distribución.');
                    }
                }
                if ($total <= 0 || $total !== Money::scaled($v['amount'])) {
                    $this->invalid('amount', 'El importe recibido debe coincidir exactamente con la suma distribuida.');
                }
                DB::table('credit_payments')->insert([
                    'id' => $v['id'], 'loan_application_id' => $loan->id, 'branch_id' => $loan->branch_id,
                    'actor_id' => $actor->id, 'actor_name' => $actor->name, 'received_on' => now('America/Cancun')->toDateString(),
                    'number' => (int) DB::table('credit_payments')->where('loan_application_id', $loan->id)->max('number') + 1,
                    'method' => $v['method'], 'amount' => $v['amount'], 'bank_name' => $v['bank_name'], 'bank_reference' => $v['bank_reference'],
                    'deposited_on' => $v['deposited_on'], 'deposit_key' => $depositKey, 'notes' => $v['notes'], 'request_hash' => $data->fingerprint(), 'created_at' => now(),
                ]);
                if ($depositKey) {
                    DB::table('active_bank_payment_references')->insert(['deposit_key' => $depositKey, 'credit_payment_id' => $v['id']]);
                }
                foreach ($allocations as $allocation) {
                    DB::table('credit_payment_allocations')->insert($allocation);
                }
                foreach ($rows as $row) {
                    DB::table('loan_installments')->where('id', $row->id)->update(['amount_paid' => $row->amount_paid]);
                }
                if ($rows->every(fn ($r) => Money::scaled((string) $r->amount_paid) === Money::scaled((string) $r->amount_due))) {
                    $loan->status = 'pagado';
                    $loan->save();
                }

                return $loan;
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            abort(409, 'El cobro o el ticket ya se registró. Actualiza antes de reintentar.');
        }
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
