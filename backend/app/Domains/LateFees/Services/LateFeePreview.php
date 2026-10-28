<?php

namespace App\Domains\LateFees\Services;

use App\Domains\Credit\Services\CreditCalculator as Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Read-only simulation. It never creates charges or alters the collectible balance. */
final class LateFeePreview
{
    // Invoke only after access validation and while holding the loan lock.
    public function assessments(string $loanId, Collection $installments, string $asOf, ?Collection $events = null): array
    {
        if ($events === null) {
            $payments = DB::table('credit_payment_allocations as a')
                ->join('credit_payments as p', 'p.id', '=', 'a.credit_payment_id')
                ->leftJoin('credit_payment_reversals as r', 'r.credit_payment_id', '=', 'p.id')
                ->where('p.loan_application_id', $loanId)->whereNull('r.id')
                ->get(['a.loan_installment_id', 'a.amount', 'p.received_on as applied_on']);
            $guarantees = DB::table('guarantee_allocations as a')
                ->join('guarantee_applications as g', 'g.id', '=', 'a.guarantee_application_id')
                ->leftJoin('guarantee_reversals as r', 'r.guarantee_application_id', '=', 'g.id')
                ->where('g.loan_application_id', $loanId)->whereNull('r.id')
                ->get(['a.loan_installment_id', 'a.amount', 'g.applied_on']);
            $events = $payments->concat($guarantees)->groupBy('loan_installment_id');
        }
        $calculator = app(WeeklyLateFeeCalculator::class);
        $details = [];
        foreach ($installments as $installment) {
            $due = Money::scaled((string) $installment->amount_due);
            $history = ($events[$installment->id] ?? collect())->sortBy('applied_on')->values();
            abort_unless($history->sum(fn ($event) => Money::scaled((string) $event->amount)) === Money::scaled((string) $installment->amount_paid), 409, 'El historial aplicado a una cuota no coincide con su saldo. Solicita revisión.');
            $bases = [];
            $paid = $index = 0;
            foreach ($calculator->chargeDates($installment->due_on, $asOf) as $date) {
                // At the beginning of the day: receipts registered on that day reduce the next weekly base.
                while ($index < $history->count() && $history[$index]->applied_on < $date) {
                    $paid += Money::scaled((string) $history[$index]->amount);
                    $index++;
                }
                abort_unless($paid <= $due, 409, 'Las aplicaciones históricas superan el importe de la cuota.');
                $bases[$date] = $due - $paid;
            }
            $assessment = $calculator->assess($installment->due_on, $asOf, $bases);
            foreach ($assessment['charges'] as $charge) {
                $details[] = ['loan_installment_id' => $installment->id, 'client_id' => $installment->client_id,
                    'number' => $installment->number, 'due_on' => $installment->due_on,
                    'charged_on' => $charge['charged_on'], 'week' => $charge['week'],
                    'base' => Money::money($charge['base_cents']), 'amount' => Money::money($charge['amount_cents'])];
            }
        }

        return $details;
    }

    public function forLoan(string $loanId, Collection $installments, string $asOf): array
    {
        $total = 0;
        $details = [];
        foreach (collect($this->assessments($loanId, $installments, $asOf))->groupBy('loan_installment_id') as $rows) {
            $positive = $rows->filter(fn ($row) => Money::scaled($row['amount']) > 0)->values();
            if ($positive->isEmpty()) {
                continue;
            }
            $first = $positive->first();
            $amount = $positive->sum(fn ($row) => Money::scaled($row['amount']));
            $total += $amount;
            $details[] = ['client_id' => $first['client_id'], 'number' => $first['number'], 'due_on' => $first['due_on'],
                'amount' => Money::money($amount), 'charges' => $positive->map(fn ($row) => array_intersect_key($row, array_flip(['week', 'charged_on', 'base', 'amount'])))->all()];
        }

        return [
            'mode' => 'simulation', 'policy' => WeeklyLateFeeCalculator::POLICY,
            'weekly_percent' => 10, 'as_of' => $asOf,
            'total' => Money::money($total), 'installments' => $details,
        ];
    }
}
