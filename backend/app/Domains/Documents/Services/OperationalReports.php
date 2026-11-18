<?php

namespace App\Domains\Documents\Services;

use App\Domains\Credit\Services\CreditAccess;
use App\Domains\Credit\Services\CreditCalculator as Money;
use App\Domains\Credit\Services\CreditPortfolio;
use App\Domains\Credit\Services\CreditStatement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class OperationalReports
{
    public function render(User $actor, string $kind, array $filters): string
    {
        return DB::transaction(fn () => $this->renderSnapshot($actor, $kind, $filters), 3);
    }

    private function renderSnapshot(User $actor, string $kind, array $filters): string
    {
        app(CreditAccess::class)->authorize($actor);
        $data = ['kind' => $kind, 'as_of' => now('America/Cancun')->toDateString()];
        if ($kind === 'portfolio') {
            $report = app(CreditPortfolio::class)->payload($actor, $filters, false);
            $loans = app(CreditAccess::class)->query($actor)->whereIn('id', collect($report['data'])->pluck('id'))->get()->keyBy('id');
            $promoters = User::query()->whereIn('id', $loans->pluck('promoter_id'))->pluck('name', 'id');
            $applied = DB::table('guarantee_applications as g')->whereIn('g.loan_application_id', $loans->keys())
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('guarantee_reversals as r')->whereColumn('r.guarantee_application_id', 'g.id'))
                ->select('g.loan_application_id')->selectRaw('SUM(ROUND(g.amount * 100, 0)) as cents')->groupBy('g.loan_application_id')->pluck('cents', 'g.loan_application_id');
            $availableGuarantees = $loans->map(function ($loan) use ($applied) {
                $retained = $loan->approved_snapshot['totals']['guarantee'] ?? null;
                $available = $retained === null ? -1 : Money::scaled($retained) - (int) ($applied[$loan->id] ?? 0);

                return $available < 0 ? 'N/R' : Money::money($available);
            });
            $data += ['report' => $report, 'loans' => $loans, 'promoters' => $promoters, 'available_guarantees' => $availableGuarantees];
        } elseif ($kind === 'agenda') {
            $date = CarbonImmutable::parse($filters['week_on'] ?? $data['as_of'])->startOfWeek();
            $data += ['week_on' => $date->toDateString(), 'week_until' => $date->addDays(4)->toDateString(), 'actor_name' => $actor->name];
        } else {
            $id = $filters['credit_id'] ?? '';
            $loan = app(CreditAccess::class)->query($actor)->with('team')->findOrFail($id);
            $statement = app(CreditStatement::class)->payload($actor, $id);
            $data += ['loan' => $loan, 'statement' => $statement, 'promoter' => User::query()->whereKey($loan->promoter_id)->value('name')];
            if ($kind === 'deposits') {
                // Bank references are restricted to the same cash/management profiles that handle them.
                abort_unless($actor->hasRole('Super Admin') || $actor->can('credit.collect') || $actor->can('cash.view'), 403);
                $data['deposits'] = DB::table('credit_payments as p')->where('p.loan_application_id', $id)->where('p.method', 'bank_deposit')
                    ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('credit_payment_reversals as r')->whereColumn('r.credit_payment_id', 'p.id'))
                    ->orderBy('p.number')->get(['p.number', 'p.received_on', 'p.deposited_on', 'p.bank_name', 'p.bank_reference', 'p.amount']);
            }
            if ($kind === 'collection_log') {
                abort_unless($actor->hasRole('Super Admin') || $actor->can('credit.collect') || $actor->can('credit.review'), 403);
                $clientId = $filters['client_id'] ?? '';
                $member = collect($statement['members'])->firstWhere('client_id', $clientId);
                abort_unless($member, 422, 'Selecciona una integrante de este crédito.');
                $oldest = collect($statement['installments'])->filter(fn ($i) => $i['client_id'] === $clientId && $i['due_on'] < $data['as_of'] && Money::scaled($i['amount_due']) > Money::scaled($i['amount_paid']))->min('due_on');
                $data += ['member' => $member, 'first_overdue_on' => $oldest, 'client' => DB::table('clients')->where('id', $clientId)->first(['address', 'phone'])];
            }
            if ($kind === 'statement') {
                $clientId = $filters['client_id'] ?? null;
                abort_if($clientId && ! collect($statement['members'])->contains('client_id', $clientId), 422, 'Integrante ajena al crédito.');
                $members = collect($statement['members'])->when($clientId, fn ($rows) => $rows->where('client_id', $clientId));
                $ids = $members->pluck('client_id');
                $total = $members->sum(fn ($m) => Money::scaled($m['total_repayment']));
                $balance = $total;
                $rows = [];
                for ($number = 1; $number <= 16; $number++) {
                    $installments = collect($statement['installments'])->where('number', $number)->whereIn('client_id', $ids);
                    $due = $installments->first()['due_on'];
                    $amount = $installments->sum(fn ($i) => Money::scaled($i['amount_due']));
                    $paid = $installments->sum(fn ($i) => Money::scaled($i['amount_paid']));
                    $balance -= $paid;
                    $payments = collect($statement['payments'])->filter(fn ($p) => ! $p->reversed_on && collect($p->allocations)->contains(fn ($a) => ($a->kind ?? 'installment') !== 'late_fee' && (int) $a->number === $number && $ids->contains($a->client_id)));
                    $guarantees = collect($statement['guarantees'])->filter(fn ($g) => ! $g['reversed_on'] && collect($g['allocations'])->contains(fn ($a) => (int) $a['number'] === $number && $ids->contains($a['client_id'])));
                    $date = $payments->pluck('received_on')->merge($guarantees->pluck('applied_on'))->max();
                    $reference = $paid < $amount ? $data['as_of'] : ($date ?? $due);
                    $days = max(0, (int) CarbonImmutable::parse($due)->diffInDays(CarbonImmutable::parse($reference), false));
                    $rows[] = ['number' => $number, 'due_on' => $due, 'paid_on' => $date, 'payment_numbers' => $payments->pluck('number')->implode(', '), 'days' => $days, 'due' => Money::money($amount), 'paid' => Money::money($paid), 'balance' => Money::money($balance)];
                }
                $data += ['rows' => $rows, 'selected_members' => $members, 'total' => Money::money($total)];
            }
        }

        return view('reports.reference', $data)->render();
    }
}
