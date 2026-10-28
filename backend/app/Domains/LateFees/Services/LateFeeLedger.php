<?php

namespace App\Domains\LateFees\Services;

use App\Domains\Credit\Services\CreditCalculator as Money;
use App\Models\LoanApplication;
use Carbon\CarbonImmutable;
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
        if ($policy !== null) {
            abort_unless(is_array($policy), 409, 'La política de mora requiere una versión de cálculo compatible.');
            // MySQL normalizes JSON object key order. Values and list order remain strict.
            $expected = self::policy();
            ksort($policy);
            ksort($expected);
            abort_unless($policy === $expected, 409, 'La política de mora requiere una versión de cálculo compatible.');
        }

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

        return $this->calculate($loan, $rows, $saved, $paid, null, $persist);
    }

    /** Read-only, bounded batch. Caller supplies only loans authorized for the current user. */
    public function statesFor(Collection $loans): Collection
    {
        $loans = $loans->filter(fn ($loan) => $this->enabled($loan));
        if ($loans->isEmpty()) {
            return collect();
        }
        $ids = $loans->pluck('id');
        $installments = DB::table('loan_installments')->whereIn('loan_application_id', $ids)->get()->groupBy('loan_application_id');
        $saved = DB::table('weekly_late_fees')->whereIn('loan_application_id', $ids)->get()->groupBy('loan_application_id');
        $paid = DB::table('late_fee_payment_allocations as a')->join('credit_payments as p', 'p.id', '=', 'a.credit_payment_id')
            ->whereIn('p.loan_application_id', $ids)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('credit_payment_reversals as r')->whereColumn('r.credit_payment_id', 'p.id'))
            ->get(['a.weekly_late_fee_id', 'a.amount'])->groupBy('weekly_late_fee_id');
        $payments = DB::table('credit_payment_allocations as a')->join('credit_payments as p', 'p.id', '=', 'a.credit_payment_id')
            ->whereIn('p.loan_application_id', $ids)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('credit_payment_reversals as r')->whereColumn('r.credit_payment_id', 'p.id'))
            ->get(['a.loan_installment_id', 'a.amount', 'p.received_on as applied_on']);
        $guarantees = DB::table('guarantee_allocations as a')->join('guarantee_applications as g', 'g.id', '=', 'a.guarantee_application_id')
            ->whereIn('g.loan_application_id', $ids)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('guarantee_reversals as r')->whereColumn('r.guarantee_application_id', 'g.id'))
            ->get(['a.loan_installment_id', 'a.amount', 'g.applied_on']);
        $events = $payments->concat($guarantees)->groupBy('loan_installment_id');

        return $loans->mapWithKeys(fn ($loan) => [$loan->id => $this->calculate($loan,
            $installments->get($loan->id, collect()),
            $saved->get($loan->id, collect())->keyBy(fn ($row) => $row->loan_installment_id.':'.$row->charged_on),
            $paid, $events, false)]);
    }

    private function calculate(LoanApplication $loan, Collection $rows, Collection $saved, Collection $paid, ?Collection $events, bool $persist): array
    {
        $result = [];
        foreach (app(LateFeePreview::class)->assessments($loan->id, $rows, now('America/Cancun')->toDateString(), $events) as $row) {
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
            if ($fee > 0) {
                $oldest = collect($state['charges'])->where('client_id', $member['client_id'])->filter(fn ($r) => Money::scaled($r['balance']) > 0)->min('charged_on');
                $days = (int) CarbonImmutable::parse($oldest, 'America/Cancun')->diffInDays(now('America/Cancun')->startOfDay()) + 1;
                $member['days_overdue'] = max($member['days_overdue'], $days);
            }
            foreach (['balance', 'due', 'overdue'] as $key) {
                $member[$key] = Money::money(Money::scaled($member[$key]) + $fee);
            }

            return $member;
        });
    }
}
