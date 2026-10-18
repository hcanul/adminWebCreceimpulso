<?php

namespace App\Domains\Guarantees\Services;

use App\Domains\Credit\Services\CreditAccess;
use App\Domains\Credit\Services\CreditCalculator as Money;
use App\Domains\Credit\Services\CreditCollections;
use App\Domains\Credit\Services\CreditDisbursement;
use App\Models\LoanApplication;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class GuaranteeBalance
{
    public function activeAllocations(string $id)
    {
        return DB::table('guarantee_allocations as a')->join('guarantee_applications as g', 'g.id', '=', 'a.guarantee_application_id')->where('g.loan_application_id', $id)->whereNotExists(fn ($q) => $q->selectRaw('1')->from('guarantee_reversals as r')->whereColumn('r.guarantee_application_id', 'g.id'))->orderBy('a.id')->get(['a.*']);
    }

    public function payload(User $actor, string $id): array
    {
        $visible = app(CreditAccess::class)->query($actor)->findOrFail($id);

        return DB::transaction(function () use ($visible) {
            $loan = LoanApplication::whereKey($visible->id)->lockForUpdate()->firstOrFail();

            return $this->forLoan($loan);
        });
    }

    public function forLoan(LoanApplication $loan): array
    {
        abort_unless(in_array($loan->status, ['entregado', 'pagado'], true), 409, 'El crédito debe estar entregado.');
        $delivery = DB::table('credit_disbursements')->where('loan_application_id', $loan->id)->first();
        abort_unless($delivery && $loan->approved_snapshot, 409, 'Primero registra el desembolso y la retención.');
        $rows = app(CreditCollections::class)->rows($loan->id);
        $active = $this->activeAllocations($loan->id);
        $members = [];
        abort_unless($rows->count() === count($loan->approved_snapshot['members']) * 16, 409, 'Calendario incompleto.');
        $expected = collect(app(CreditDisbursement::class)->schedule($loan->approved_snapshot, $delivery->first_due_on))->keyBy(fn ($r) => $r['client_id'].':'.$r['number']);
        foreach ($rows as $row) {
            $reference = $expected[$row->client_id.':'.$row->number] ?? null;
            abort_unless($reference && $row->due_on === $reference['due_on'] && Money::money(Money::scaled((string) $row->amount_due)) === $reference['amount_due'] && Money::scaled((string) $row->amount_paid) <= Money::scaled((string) $row->amount_due), 409, 'El calendario no coincide con las condiciones originales.');
        }
        $retainedTotal = 0;
        foreach ($loan->approved_snapshot['members'] as $m) {
            $retained = Money::scaled($m['estimate']['guarantee']);
            $retainedTotal += $retained;
            $applied = $active->where('client_id', $m['client_id'])->sum(fn ($a) => Money::scaled((string) $a->amount));
            $balance = $rows->where('client_id', $m['client_id'])->sum(fn ($r) => Money::scaled((string) $r->amount_due) - Money::scaled((string) $r->amount_paid));
            abort_unless($applied <= $retained && $balance >= 0, 409, 'Revisa la integridad de la garantía.');
            $members[] = ['client_id' => $m['client_id'], 'name' => $m['name'], 'folio' => $m['folio'], 'retained' => Money::money($retained), 'applied' => Money::money($applied), 'available' => Money::money($retained - $applied), 'balance' => Money::money($balance), 'applicable' => Money::money(min($balance, $retained - $applied))];
        }
        abort_unless($retainedTotal === Money::scaled((string) $delivery->guarantee_retained), 409, 'La retención no coincide con lo autorizado.');
        $history = DB::table('guarantee_applications')->where('loan_application_id', $loan->id)->orderBy('created_at')->get(['id', 'actor_name', 'amount', 'reason', 'applied_on', 'created_at']);
        $reversals = DB::table('guarantee_reversals')->whereIn('guarantee_application_id', $history->pluck('id'))->get(['guarantee_application_id', 'actor_name', 'reason', 'reversed_on'])->keyBy('guarantee_application_id');
        $allocations = DB::table('guarantee_allocations as a')->join('loan_installments as i', 'i.id', '=', 'a.loan_installment_id')->whereIn('guarantee_application_id', $history->pluck('id'))->get(['a.guarantee_application_id', 'a.client_id', 'a.amount', 'i.number', 'i.due_on'])->groupBy('guarantee_application_id');
        foreach ($history as $event) {
            abort_unless(collect($allocations[$event->id] ?? [])->sum(fn ($a) => Money::scaled((string) $a->amount)) === Money::scaled((string) $event->amount), 409, 'El detalle de garantía no coincide con su aplicación.');
            $event->amount = Money::money(Money::scaled((string) $event->amount));
            $event->reversal = $reversals[$event->id] ?? null;
            $event->allocations = $allocations[$event->id] ?? [];
        }
        $availableOn = CarbonImmutable::parse($delivery->first_due_on)->addWeeks(15)->toDateString();
        $today = now('America/Cancun')->toDateString();

        return ['credit_folio' => $loan->folio, 'status' => $loan->status, 'as_of' => $today, 'available_on' => $availableOn, 'eligible_date' => $today >= $availableOn, 'version' => hash('sha256', app(CreditCollections::class)->version($rows).$active->toJson()), 'members' => $members, 'history' => $history];
    }
}
