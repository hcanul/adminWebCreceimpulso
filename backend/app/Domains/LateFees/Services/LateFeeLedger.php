<?php

namespace App\Domains\LateFees\Services;

use App\Domains\Credit\Services\CreditCalculator as Money;
use App\Models\LoanApplication;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final class LateFeeLedger
{
    public static function policy(): array
    {
        return ['id' => WeeklyLateFeeCalculator::POLICY, 'weekly_percent' => 10, 'starts' => 'day_after_due',
            'base' => 'unpaid_installment_at_day_start', 'compounds' => false,
            'payment_order' => ['due_installments', 'late_fees', 'future_installments'], 'timezone' => 'America/Cancun'];
    }

    public function enabled(LoanApplication $loan): bool
    {
        $policy = $loan->approved_snapshot['late_fee_policy'] ?? null;
        abort_if($policy && $policy !== self::policy(), 409, 'La política de mora requiere una versión de cálculo compatible.');

        return (bool) $policy;
    }

    /** Must be called under the same loan lock as collection/reversal/guarantee mutations. */
    public function state(LoanApplication $loan, Collection $rows, bool $persist = false): array
    {
        if (! $this->enabled($loan)) {
            return ['enabled' => false, 'total' => '0.00', 'paid' => '0.00', 'balance' => '0.00', 'charges' => []];
        }
        $saved = DB::table('weekly_late_fees')->where('loan_application_id', $loan->id)->get()->keyBy(fn ($row) => $row->loan_installment_id.':'.$row->charged_on);
        $paid = DB::table('late_fee_payment_allocations as a')->join('credit_payments as p', 'p.id', '=', 'a.credit_payment_id')
            ->where('p.loan_application_id', $loan->id)->whereNotExists(fn ($q) => $q->selectRaw('1')->from('credit_payment_reversals as r')->whereColumn('r.credit_payment_id', 'p.id'))
            ->get(['a.weekly_late_fee_id', 'a.amount'])->groupBy('weekly_late_fee_id');
        $result = [];
        foreach (app(LateFeePreview::class)->assessments($loan->id, $rows, now('America/Cancun')->toDateString()) as $row) {
            $key = $row['loan_installment_id'].':'.$row['charged_on'];
            $existing = $saved->get($key);
            $id = $existing?->id ?? Uuid::uuid5(Uuid::NAMESPACE_URL, 'crece:late-fee:'.$key.':'.WeeklyLateFeeCalculator::POLICY)->toString();
            $base = $existing ? Money::money(Money::scaled((string) $existing->base)) : $row['base'];
            $amount = $existing ? Money::money(Money::scaled((string) $existing->amount)) : $row['amount'];
            if ($persist && ! $existing) {
                // Zero assessments also freeze weeks settled before the cutoff.
                DB::table('weekly_late_fees')->insert(['id' => $id, 'branch_id' => $loan->branch_id, 'loan_application_id' => $loan->id,
                    'loan_installment_id' => $row['loan_installment_id'], 'client_id' => $row['client_id'], 'policy' => WeeklyLateFeeCalculator::POLICY,
                    'charged_on' => $row['charged_on'], 'base' => $base, 'amount' => $amount, 'created_at' => now()]);
            }
            $applied = ($paid[$id] ?? collect())->sum(fn ($part) => Money::scaled((string) $part->amount));
            abort_unless($applied <= Money::scaled($amount), 409, 'Las aplicaciones de mora superan el cargo.');
            $result[] = array_merge($row, ['id' => $id, 'base' => $base, 'amount' => $amount, 'paid' => Money::money($applied), 'balance' => Money::money(Money::scaled($amount) - $applied)]);
        }
        $result = collect($result)->sortBy(fn ($r) => $r['charged_on'].':'.$r['loan_installment_id'])->values()->all();
        $sum = fn ($key) => Money::money(array_sum(array_map(fn ($r) => Money::scaled($r[$key]), $result)));

        return ['enabled' => true, 'policy' => self::policy(), 'total' => $sum('amount'), 'paid' => $sum('paid'), 'balance' => $sum('balance'), 'charges' => $result];
    }

    public function allocations(array $paymentIds): Collection
    {
        return DB::table('late_fee_payment_allocations as a')->join('weekly_late_fees as f', 'f.id', '=', 'a.weekly_late_fee_id')
            ->join('loan_installments as i', 'i.id', '=', 'f.loan_installment_id')->whereIn('a.credit_payment_id', $paymentIds)
            ->orderBy('f.charged_on')->orderBy('a.id')->get(['a.credit_payment_id', 'a.payer_client_id', 'a.amount', 'f.client_id', 'f.charged_on', 'i.number', 'i.due_on'])
            ->map(function ($row) {
                $row->kind = 'late_fee';
                $row->amount = Money::money(Money::scaled((string) $row->amount));

                return $row;
            });
    }

    public function members(Collection $members, array $state): Collection
    {
        return $members->map(function ($member) use ($state) {
            $fee = collect($state['charges'])->where('client_id', $member['client_id'])->sum(fn ($row) => Money::scaled($row['balance']));
            $member['installment_balance'] = $member['balance'];
            $member['installment_due'] = $member['due'];
            $member['late_fee_balance'] = Money::money($fee);
            foreach (['balance', 'due', 'overdue'] as $key) {
                $member[$key] = Money::money(Money::scaled($member[$key]) + $fee);
            }

            return $member;
        });
    }
}
