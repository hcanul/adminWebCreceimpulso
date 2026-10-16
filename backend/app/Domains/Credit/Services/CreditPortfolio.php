<?php

namespace App\Domains\Credit\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class CreditPortfolio
{
    public function payload(User $actor, array $filters): array
    {
        $access = app(CreditAccess::class);
        $access->authorize($actor);

        return DB::transaction(function () use ($actor, $filters, $access) {
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
            match ($filters['status'] ?? 'all') {
                'overdue' => $q->where('balances.overdue', '>', 0),
                'today' => $q->where('balances.due_today', '>', 0),
                'current' => $q->where('balances.overdue', '=', 0),
                default => null,
            };
            $totals = (clone $q)->selectRaw('COUNT(*) as credits, COALESCE(SUM(balance), 0) as balance, COALESCE(SUM(due), 0) as due, COALESCE(SUM(overdue), 0) as overdue, COALESCE(SUM(due_today), 0) as due_today')->first();
            $money = fn ($value) => CreditCalculator::money((int) $value);
            $summary = ['credits' => (int) $totals->credits];
            foreach (['balance', 'due', 'overdue', 'due_today'] as $key) {
                $summary[$key] = $money($totals->$key);
            }
            $page = $q->select(['loans.id', 'loans.folio', 'teams.name as group', 'branches.name as branch', 'balances.*'])
                ->orderByRaw('CASE WHEN oldest_due IS NULL THEN 1 ELSE 0 END')->orderBy('oldest_due')->orderBy('loans.id')
                ->paginate(25, ['*'], 'page', $filters['page'] ?? 1);
            $rows = $page->getCollection()->map(function ($row) use ($money, $today) {
                foreach (['balance', 'due', 'overdue', 'due_today'] as $key) {
                    $row->$key = $money($row->$key);
                }
                $row->days_overdue = $row->oldest_due ? (int) CarbonImmutable::parse($row->oldest_due, 'America/Cancun')->diffInDays(CarbonImmutable::parse($today, 'America/Cancun')) : 0;
                unset($row->loan_application_id);

                return $row;
            });

            return ['as_of' => $today, 'summary' => $summary, 'data' => $rows, 'page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()];
        });
    }
}
