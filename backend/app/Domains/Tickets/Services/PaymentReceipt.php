<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Credit\Services\CreditCalculator;
use App\Domains\Credit\Services\CreditCollections;
use App\Models\LoanApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

final class PaymentReceipt
{
    public function payload(string $id, bool $private = false): array
    {
        $payment = DB::table('credit_payments')->where('id', $id)->first();
        abort_unless($payment, 404);
        $loan = LoanApplication::withTrashed()->findOrFail($payment->loan_application_id);
        $reversal = DB::table('credit_payment_reversals')->where('credit_payment_id', $id)->first();
        $data = [
            'id' => $payment->id,
            'folio' => 'REC-'.$loan->folio.'-'.str_pad((string) $payment->number, 4, '0', STR_PAD_LEFT),
            'credit_folio' => $loan->folio,
            'amount' => CreditCalculator::money(CreditCalculator::scaled((string) $payment->amount)),
            'received_on' => $payment->received_on,
            'method' => $payment->method,
            'status' => $reversal ? 'reversed' : 'registered',
            'reversed_on' => $reversal?->reversed_on,
        ];
        if (! $private) {
            return $data;
        }
        $members = collect($loan->approved_snapshot['members'] ?? [])->keyBy('client_id');
        $data += [
            'balance_version' => app(CreditCollections::class)->version(app(CreditCollections::class)->rows($loan->id)),
            'reversal' => $reversal ? ['id' => $reversal->id, 'actor_name' => $reversal->actor_name, 'reason' => $reversal->reason, 'reversed_on' => $reversal->reversed_on] : null,
            'actor_name' => $payment->actor_name,
            'bank_name' => $payment->bank_name,
            'bank_reference' => $payment->bank_reference,
            'deposited_on' => $payment->deposited_on,
            'verification_url' => URL::signedRoute('receipts.verify', ['id' => $id]),
            'allocations' => DB::table('credit_payment_allocations as a')
                ->join('loan_installments as i', 'i.id', '=', 'a.loan_installment_id')
                ->where('a.credit_payment_id', $id)->orderBy('i.client_id')->orderBy('i.number')->orderBy('a.id')
                ->get(['i.client_id', 'i.number', 'i.due_on', 'a.payer_client_id', 'a.amount'])
                ->map(fn ($r) => ['beneficiary' => $members[$r->client_id]['name'] ?? 'Integrante', 'payer' => $members[$r->payer_client_id]['name'] ?? 'Integrante', 'number' => $r->number, 'due_on' => $r->due_on, 'amount' => CreditCalculator::money(CreditCalculator::scaled((string) $r->amount))]),
        ];

        return $data;
    }
}
