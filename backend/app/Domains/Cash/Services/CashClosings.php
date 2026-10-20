<?php

namespace App\Domains\Cash\Services;

use App\Domains\Credit\Services\CreditCalculator as Money;
use App\Domains\Intake\Services\IntakeAccess;
use App\Domains\LateFees\Services\LateFeeLedger;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CashClosings
{
    public function authorize(User $actor, string $permission = 'cash.view'): void
    {
        abort_unless($actor->is_active && ! $actor->deleted_at && ($actor->hasRole('Super Admin') || $actor->can($permission)), 403);
    }

    public function authorizeBranch(User $actor, string $branchId): void
    {
        abort_unless(app(IntakeAccess::class)->global($actor) || $actor->branch_id === $branchId, 404);
        abort_unless(DB::table('branches')->where('id', $branchId)->exists(), 404);
    }

    public function query(User $actor): Builder
    {
        $this->authorize($actor);
        $q = DB::table('cash_closings');
        if (! app(IntakeAccess::class)->global($actor)) {
            $q->where('branch_id', $actor->branch_id ?? '');
        }
        if (! $actor->hasRole('Super Admin') && ! $actor->can('cash.audit')) {
            $q->where('actor_id', $actor->id);
        }

        return $q;
    }

    public function pending(User $actor, string $branch, string $date): Builder
    {
        return DB::table('credit_payments as p')->where('p.branch_id', $branch)->where('p.actor_id', $actor->id)->where('p.received_on', $date)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('credit_payment_reversals as r')->whereColumn('r.credit_payment_id', 'p.id'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('cash_closing_payments as c')->whereColumn('c.credit_payment_id', 'p.id'));
    }

    public function summary(Collection $rows): array
    {
        $cash = $bank = 0;
        foreach ($rows as $r) {
            if ($r->method === 'cash_branch') {
                $cash += Money::scaled((string) $r->amount);
            } elseif ($r->method === 'bank_deposit') {
                $bank += Money::scaled((string) $r->amount);
            } else {
                abort(409, 'Existe un cobro con modalidad desconocida; revisa antes de cerrar.');
            }
        }

        return ['cash_total' => Money::money($cash), 'bank_total' => Money::money($bank), 'payment_count' => $rows->count(),
            'version' => hash('sha256', $rows->map(fn ($r) => [$r->id, $r->method, Money::money(Money::scaled((string) $r->amount))])->toJson())];
    }

    public function preview(User $actor, string $branch, string $date, int $page): array
    {
        $this->authorize($actor, 'cash.close');
        $this->authorizeBranch($actor, $branch);

        return DB::transaction(function () use ($actor, $branch, $date, $page) {
            // Collection and closing use this same lock: a payment cannot slip into a reviewed batch.
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $rows = $this->pending($actor, $branch, $date)->orderBy('p.id')->get(['p.id', 'p.method', 'p.amount']);

            return $this->summary($rows) + ['payments' => $this->paymentDetails($this->pending($actor, $branch, $date), $page)];
        });
    }

    public function paymentDetails(Builder $query, int $page)
    {
        $page = $query->join('loan_applications as l', 'l.id', '=', 'p.loan_application_id')
            ->orderBy('p.created_at')->orderBy('p.id')->paginate(30, ['p.id', 'p.received_on', 'p.method', 'p.amount', 'p.bank_name', 'p.bank_reference', 'p.deposited_on', 'l.folio as credit_folio', 'p.number'], 'page', $page);
        $page->setCollection($this->withBreakdown($page->getCollection()));
        return $page;
    }

    public function withBreakdown(Collection $rows): Collection
    {
        $fees = app(LateFeeLedger::class)->allocations($rows->map(fn ($row) => $row->credit_payment_id ?? $row->id)->all())->groupBy('credit_payment_id');
        return $rows->map(function ($row) use ($fees) {
            $id = $row->credit_payment_id ?? $row->id;
            $total = Money::scaled((string) $row->amount);
            $fee = ($fees[$id] ?? collect())->sum(fn ($a) => Money::scaled($a->amount));
            abort_unless($fee <= $total, 409, 'El desglose de mora no coincide con el cobro.');
            $row->amount = Money::money($total);
            $row->late_fee_amount = Money::money($fee);
            $row->installment_amount = Money::money($total - $fee);
            return $row;
        });
    }

    public function normalize(object $row): object
    {
        unset($row->request_hash);
        $row->closed_at_local = CarbonImmutable::parse($row->created_at, 'UTC')->setTimezone('America/Cancun')->format('Y-m-d H:i:s');
        foreach (['cash_total', 'bank_total', 'cash_counted', 'cash_difference'] as $key) {
            $value = (string) $row->$key;
            $row->$key = (str_starts_with($value, '-') ? '-' : '').Money::money(Money::scaled(ltrim($value, '-')));
        }

        return $row;
    }

    public function report(User $actor, string $id): array
    {
        // Closed records and their membership are immutable. Read reversals once so
        // their rows and totals always describe the same set, even during a reversal.
        $row = $this->query($actor)->where('id', $id)->first();
        abort_unless($row, 404);
        $payments = DB::table('credit_payments as p')
            ->join('cash_closing_payments as c', 'c.credit_payment_id', '=', 'p.id')
            ->join('loan_applications as l', 'l.id', '=', 'p.loan_application_id')
            ->where('c.cash_closing_id', $id)->orderBy('p.created_at')->orderBy('p.id')
            ->get(['p.id', 'p.received_on', 'p.method', 'p.amount', 'p.bank_name', 'p.bank_reference', 'p.deposited_on', 'l.folio as credit_folio', 'p.number'])
            ->map(function ($payment) {
                $payment->amount = Money::money(Money::scaled((string) $payment->amount));

                return $payment;
            });
        $payments = $this->withBreakdown($payments);
        $record = $this->normalize($row);
        $totals = $this->summary($payments);
        abort_unless($totals['payment_count'] === (int) $record->payment_count
            && $totals['cash_total'] === $record->cash_total
            && $totals['bank_total'] === $record->bank_total, 409, 'El detalle del corte no coincide con su cierre. Solicita revisión antes de imprimir.');

        $adjustments = DB::table('credit_payment_reversals as r')
            ->join('cash_closing_payments as c', 'c.credit_payment_id', '=', 'r.credit_payment_id')
            ->join('credit_payments as p', 'p.id', '=', 'r.credit_payment_id')
            ->join('loan_applications as l', 'l.id', '=', 'p.loan_application_id')
            ->where('c.cash_closing_id', $id)->orderBy('r.created_at')->orderBy('r.id')
            ->get(['l.folio as credit_folio', 'p.number', 'r.id', 'r.credit_payment_id', 'r.actor_name', 'r.amount', 'r.method', 'r.reason', 'r.reversed_on'])
            ->map(function ($adjustment) {
                $adjustment->amount = Money::money(Money::scaled((string) $adjustment->amount));

                return $adjustment;
            });
        $adjustments = $this->withBreakdown($adjustments);
        $adjustmentTotals = $this->summary($adjustments);

        return [
            'record' => $record,
            'payments' => $payments,
            'adjustments' => $adjustments,
            'adjustment_totals' => [
                'cash_total' => $adjustmentTotals['cash_total'],
                'bank_total' => $adjustmentTotals['bank_total'],
                'count' => $adjustments->count(),
            ],
            'generated_at_local' => now('America/Cancun')->format('Y-m-d H:i:s'),
        ];
    }

    public function detail(User $actor, string $id, int $page = 1, int $adjustmentsPage = 1): array
    {
        $row = $this->query($actor)->where('id', $id)->first();
        abort_unless($row, 404);
        $q = DB::table('credit_payments as p')->join('cash_closing_payments as c', 'c.credit_payment_id', '=', 'p.id')->where('c.cash_closing_id', $id);

        $adjustments = DB::table('credit_payment_reversals as r')->join('cash_closing_payments as c', 'c.credit_payment_id', '=', 'r.credit_payment_id')->join('credit_payments as p', 'p.id', '=', 'r.credit_payment_id')->join('loan_applications as l', 'l.id', '=', 'p.loan_application_id')->where('c.cash_closing_id', $id)->orderByDesc('r.created_at')->paginate(30, ['l.folio as credit_folio', 'p.number', 'r.id', 'r.credit_payment_id', 'r.actor_name', 'r.amount', 'r.method', 'r.reason', 'r.reversed_on'], 'adjustments_page', $adjustmentsPage)->through(function ($r) {
            $r->amount = Money::money(Money::scaled((string) $r->amount));

            return $r;
        });

        $adjustments->setCollection($this->withBreakdown($adjustments->getCollection()));
        return ['adjustments' => $adjustments, 'record' => $this->normalize($row), 'payments' => $this->paymentDetails($q, $page)];
    }
}
