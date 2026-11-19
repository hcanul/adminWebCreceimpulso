<?php

namespace App\Domains\Cash\Services;

use App\Domains\Credit\Services\CreditCalculator as Money;
use App\Domains\Intake\Services\IntakeAccess;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class CentralCashReport
{
    public function branches(User $actor)
    {
        app(CashClosings::class)->authorize($actor);

        return DB::table('branches')->when(! app(IntakeAccess::class)->global($actor), fn ($q) => $q->where('id', $actor->branch_id ?? ''))->orderBy('name')->get(['id', 'name']);
    }

    public function movements(User $actor, array $filters): Builder
    {
        $cash = app(CashClosings::class);
        $cash->authorize($actor);
        if ($filters['branch_id'] ?? null) {
            $cash->authorizeBranch($actor, $filters['branch_id']);
        }
        $payments = DB::table('credit_payments as p')->select(['p.id', 'p.id as payment_id', 'p.loan_application_id', 'p.branch_id', 'p.actor_id', 'p.received_on as event_on', 'p.created_at', 'p.method', 'p.amount', 'p.bank_name', 'p.bank_reference', 'p.deposited_on', 'p.number'])->selectRaw("'payment' as kind");
        $reversals = DB::table('credit_payment_reversals as r')->join('credit_payments as p', 'p.id', '=', 'r.credit_payment_id')
            ->select(['r.id', 'p.id as payment_id', 'p.loan_application_id', 'p.branch_id', 'p.actor_id', 'r.reversed_on as event_on', 'r.created_at', 'r.method', 'r.amount', 'p.bank_name', 'p.bank_reference', 'p.deposited_on', 'p.number'])->selectRaw("'reversal' as kind");
        $payments->whereBetween('p.received_on', [$filters['from'], $filters['to']]);
        $reversals->whereBetween('r.reversed_on', [$filters['from'], $filters['to']]);
        $events = DB::query()->fromSub($payments->unionAll($reversals), 'e')->whereBetween('e.event_on', [$filters['from'], $filters['to']]);
        if (! app(IntakeAccess::class)->global($actor)) {
            $events->where('e.branch_id', $actor->branch_id ?? '');
        }
        if (! $actor->hasRole('Super Admin') && ! $actor->can('cash.audit')) {
            $events->where('e.actor_id', $actor->id);
        }
        if ($filters['branch_id'] ?? null) {
            $events->where('e.branch_id', $filters['branch_id']);
        }
        if (($filters['method'] ?? 'all') !== 'all') {
            $events->where('e.method', $filters['method']);
        }

        if ($search = trim($filters['search'] ?? '')) {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $events->where(function ($q) use ($term) {
                $q->whereRaw("e.bank_reference LIKE ? ESCAPE '!'", [$term])->orWhereRaw("e.bank_name LIKE ? ESCAPE '!'", [$term])
                    ->orWhereExists(function ($q) use ($term) {
                        $q->selectRaw('1')->from('loan_applications as sl')->join('teams as st', 'st.id', '=', 'sl.team_id')->whereColumn('sl.id', 'e.loan_application_id')->where(fn ($w) => $w->whereRaw("sl.folio LIKE ? ESCAPE '!'", [$term])->orWhereRaw("st.name LIKE ? ESCAPE '!'", [$term]));
                    })
                    ->orWhereExists(fn ($q) => $q->selectRaw('1')->from('users as su')->whereColumn('su.id', 'e.actor_id')->whereRaw("su.name LIKE ? ESCAPE '!'", [$term]));
            });
        }

        return $events;
    }

    public function payload(User $actor, array $filters): array
    {
        return DB::transaction(function () use ($actor, $filters) {
            $query = $this->movements($actor, $filters);
            $totals = (clone $query)->join('branches as b', 'b.id', '=', 'e.branch_id')->select(['e.branch_id', 'b.name as branch'])
                ->selectRaw("SUM(CASE WHEN kind = 'payment' THEN 1 ELSE 0 END) as payment_count, SUM(CASE WHEN kind = 'reversal' THEN 1 ELSE 0 END) as reversal_count")
                ->selectRaw("SUM(CASE WHEN kind = 'payment' AND method = 'cash_branch' THEN amount ELSE 0 END) as cash_gross, SUM(CASE WHEN kind = 'payment' AND method = 'bank_deposit' THEN amount ELSE 0 END) as bank_gross")
                ->selectRaw("SUM(CASE WHEN kind = 'reversal' AND method = 'cash_branch' THEN amount ELSE 0 END) as cash_reversed, SUM(CASE WHEN kind = 'reversal' AND method = 'bank_deposit' THEN amount ELSE 0 END) as bank_reversed")
                ->groupBy('e.branch_id', 'b.name')->orderBy('b.name')->get()->keyBy('branch_id');
            $summary = array_fill_keys(['cash_gross', 'bank_gross', 'cash_reversed', 'bank_reversed'], 0);
            $branches = $this->branches($actor)->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('id', $id))->map(function ($branch) use ($totals, &$summary) {
                $total = $totals[$branch->id] ?? null;
                $row = ['branch_id' => $branch->id, 'branch' => $branch->name, 'payment_count' => (int) ($total->payment_count ?? 0), 'reversal_count' => (int) ($total->reversal_count ?? 0)];
                foreach (array_keys($summary) as $key) {
                    $value = Money::scaled((string) ($total->$key ?? '0'));
                    $summary[$key] += $value;
                    $row[$key] = Money::money($value);
                }
                $row['cash_net'] = $this->signed(Money::scaled($row['cash_gross']) - Money::scaled($row['cash_reversed']));
                $row['bank_net'] = $this->signed(Money::scaled($row['bank_gross']) - Money::scaled($row['bank_reversed']));

                return $row;
            })->values();
            $summary['cash_net'] = $summary['cash_gross'] - $summary['cash_reversed'];
            $summary['bank_net'] = $summary['bank_gross'] - $summary['bank_reversed'];
            $summary['net'] = $summary['cash_net'] + $summary['bank_net'];
            foreach ($summary as &$value) {
                $value = $this->signed($value);
            }

            return ['from' => $filters['from'], 'to' => $filters['to'], 'branches' => $branches, 'summary' => $summary, 'movements' => $this->details($query)->paginate(30)->through(function ($row) {
                $row->amount = Money::money(Money::scaled((string) $row->amount));

                return $row;
            }), 'generated_at_local' => now('America/Cancun')->format('Y-m-d H:i:s')];
        });
    }

    public function details(Builder $query): Builder
    {
        return $query->join('loan_applications as l', 'l.id', '=', 'e.loan_application_id')->join('teams as t', 't.id', '=', 'l.team_id')->join('branches as b', 'b.id', '=', 'e.branch_id')->join('users as u', 'u.id', '=', 'e.actor_id')
            ->leftJoin('cash_closing_payments as cp', 'cp.credit_payment_id', '=', 'e.payment_id')->leftJoin('cash_closings as c', 'c.id', '=', 'cp.cash_closing_id')
            ->select(['e.*', 'l.folio as credit_folio', 't.name as group', 'b.name as branch', 'u.name as cashier', 'c.folio as closing_folio'])->orderByDesc('e.event_on')->orderByDesc('e.created_at')->orderBy('e.id');
    }

    private function signed(int $cents): string
    {
        return ($cents < 0 ? '-' : '').Money::money(abs($cents));
    }
}
