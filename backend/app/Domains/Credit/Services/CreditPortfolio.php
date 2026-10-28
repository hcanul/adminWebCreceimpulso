<?php

namespace App\Domains\Credit\Services;

use App\Domains\LateFees\Services\LateFeeLedger;
use App\Models\LoanApplication;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class CreditPortfolio
{
    public function payload(User $actor, array $filters, bool $paginate = true): array
    {
        $access = app(CreditAccess::class);
        $access->authorize($actor);

        return DB::transaction(function () use ($actor, $filters, $access, $paginate) {
            $today = now('America/Cancun')->toDateString();
            $visible = $access->query($actor)->where('status', 'entregado')->select(['id', 'folio', 'team_id', 'branch_id']);
            $pending = 'CASE WHEN amount_due > amount_paid THEN ROUND((amount_due - amount_paid) * 100, 0) ELSE 0 END';
            $balances = DB::table('loan_installments')->whereIn('loan_application_id', (clone $visible)->select('id'))
                ->select('loan_application_id')->selectRaw("SUM($pending) as balance")
                ->selectRaw("SUM(CASE WHEN due_on <= ? THEN $pending ELSE 0 END) as due", [$today])
                ->selectRaw("SUM(CASE WHEN due_on < ? THEN $pending ELSE 0 END) as overdue", [$today])
                ->selectRaw("SUM(CASE WHEN due_on = ? THEN $pending ELSE 0 END) as due_today", [$today])
                ->selectRaw('MIN(CASE WHEN due_on < ? AND amount_due > amount_paid THEN due_on END) as oldest_due', [$today])
                ->selectRaw('MIN(CASE WHEN due_on >= ? AND amount_due > amount_paid THEN due_on END) as next_due', [$today])
                ->groupBy('loan_application_id');
            $q = DB::query()->fromSub($visible, 'loans')->joinSub($balances, 'balances', 'balances.loan_application_id', '=', 'loans.id')
                ->join('teams', 'teams.id', '=', 'loans.team_id')->join('branches', 'branches.id', '=', 'loans.branch_id');
            $search = trim($filters['search'] ?? '');
            if ($search !== '') {
                // Treat SQL wildcard characters as literal text, on MySQL and SQLite.
                $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
                $q->where(fn ($q) => $q->whereRaw("loans.folio LIKE ? ESCAPE '!'", [$term])->orWhereRaw("teams.name LIKE ? ESCAPE '!'", [$term])->orWhereRaw("branches.name LIKE ? ESCAPE '!'", [$term]));
            }
            // Project charges in bounded batches. Never write financial entries from a report.
            $rows = collect();
            $q->select(['loans.id', 'loans.folio', 'teams.name as group', 'branches.name as branch', 'balances.*'])
                ->orderBy('loans.id')->chunk(100, function ($batch) use (&$rows, $today) {
                    $loans = LoanApplication::query()->whereIn('id', $batch->pluck('id'))->get(['id', 'branch_id', 'approved_snapshot']);
                    $states = app(LateFeeLedger::class)->statesFor($loans);
                    foreach ($batch as $row) {
                        $state = $states->get($row->id);
                        $fee = CreditCalculator::scaled($state['balance'] ?? '0.00');
                        $row->installment_balance = (int) $row->balance;
                        $row->late_fee_balance = $fee;
                        foreach (['balance', 'due', 'overdue'] as $key) {
                            $row->$key = (int) $row->$key + $fee;
                        }
                        $row->due_today = (int) $row->due_today;
                        if ($fee > 0) {
                            $charged = collect($state['charges'])->filter(fn ($charge) => CreditCalculator::scaled($charge['balance']) > 0)->min('charged_on');
                            // A charge on day 1 represents a quota whose due date was the previous day.
                            $feeDue = CarbonImmutable::parse($charged, 'America/Cancun')->subDay()->toDateString();
                            $row->oldest_due = $row->oldest_due ? min($row->oldest_due, $feeDue) : $feeDue;
                        }
                        $row->days_overdue = $row->oldest_due ? (int) CarbonImmutable::parse($row->oldest_due, 'America/Cancun')->diffInDays(CarbonImmutable::parse($today, 'America/Cancun')) : 0;
                        unset($row->loan_application_id);
                        $rows->push($row);
                    }
                });
            $rows = $rows->filter(fn ($row) => match ($filters['status'] ?? 'all') {
                'overdue' => $row->overdue > 0,
                'today' => $row->due_today > 0,
                'current' => $row->overdue === 0,
                default => true,
            });
            $keys = ['balance', 'due', 'overdue', 'due_today', 'installment_balance', 'late_fee_balance'];
            $summary = ['credits' => $rows->count(), 'overdue_credits' => $rows->where('overdue', '>', 0)->count()];
            foreach ($keys as $key) {
                $summary[$key] = CreditCalculator::money((int) $rows->sum($key));
            }
            $page = max(1, (int) ($filters['page'] ?? 1));
            $ordered = $rows->sortBy(fn ($row) => ($row->oldest_due ?? '9999-12-31').':'.$row->id);
            $data = ($paginate ? $ordered->slice(($page - 1) * 25, 25) : $ordered)->values()->map(function ($row) use ($keys) {
                foreach ($keys as $key) {
                    $row->$key = CreditCalculator::money((int) $row->$key);
                }

                return $row;
            });

            return ['as_of' => $today, 'summary' => $summary, 'data' => $data, 'page' => $page, 'last_page' => max(1, (int) ceil($rows->count() / 25)), 'total' => $rows->count()];
        });
    }
}
