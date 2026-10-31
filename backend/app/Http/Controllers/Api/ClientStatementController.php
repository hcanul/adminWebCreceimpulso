<?php

namespace App\Http\Controllers\Api;

use App\Domains\Credit\Services\CreditCalculator;
use App\Domains\Credit\Services\CreditCollections;
use App\Domains\Credit\Services\CreditDisbursement;
use App\Domains\LateFees\Services\LateFeeLedger;
use App\Http\Controllers\Controller;
use App\Models\LoanApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ClientStatementController extends Controller
{
    public function __invoke(Request $request)
    {
        $clientId = $request->user()->client_id;
        $data = DB::transaction(function () use ($clientId) {
            $loans = LoanApplication::where('status', 'entregado')
                ->whereHas('participations', fn ($q) => $q->where('client_id', $clientId))
                ->lockForUpdate()->get();
            abort_if($loans->count() > 1, 409, 'Hay más de un crédito activo. Solicita revisión en sucursal.');
            $loan = $loans->first();
            if (! $loan) {
                return ['active' => false];
            }
            abort_unless($loan->approved_snapshot && DB::table('credit_disbursements')->where('loan_application_id', $loan->id)->exists(), 409, 'El crédito requiere revisión de su entrega.');
            $rows = app(CreditCollections::class)->rows($loan->id);
            $delivery = DB::table('credit_disbursements')->where('loan_application_id', $loan->id)->first();
            $expected = collect(app(CreditDisbursement::class)->schedule($loan->approved_snapshot, $delivery->first_due_on))->keyBy(fn ($r) => $r['client_id'].':'.$r['number']);
            foreach ($rows as $row) {
                $reference = $expected[$row->client_id.':'.$row->number] ?? null;
                abort_unless($reference && $reference['due_on'] === $row->due_on && $reference['amount_due'] === CreditCalculator::money(CreditCalculator::scaled((string) $row->amount_due)), 409, 'El calendario no coincide con las condiciones aprobadas.');
            }
            $expectedIds = collect($loan->approved_snapshot['members'])->pluck('client_id')->sort()->values();
            abort_unless($expectedIds->contains($clientId) && $rows->pluck('client_id')->unique()->sort()->values()->all() === $expectedIds->all(), 409, 'El calendario requiere revisión.');
            foreach ($rows->groupBy('client_id') as $schedule) {
                abort_unless($schedule->count() === 16 && $schedule->pluck('number')->sort()->values()->all() === range(1, 16), 409, 'El calendario está incompleto.');
            }
            $today = now('America/Cancun')->toDateString();
            $make = function ($schedule) use ($today) {
                return $schedule->groupBy('number')->sortKeys()->map(function ($week, $number) use ($today) {
                    abort_unless($week->pluck('due_on')->unique()->count() === 1, 409, 'Fechas de calendario inconsistentes.');
                    $amount = $paid = 0;
                    foreach ($week as $row) {
                        $due = CreditCalculator::scaled((string) $row->amount_due);
                        $applied = CreditCalculator::scaled((string) $row->amount_paid);
                        abort_unless($due >= 0 && $applied >= 0 && $applied <= $due, 409, 'Los saldos requieren revisión.');
                        $amount += $due;
                        $paid += $applied;
                    }
                    $remaining = $amount - $paid;
                    $date = $week->first()->due_on;
                    $status = $remaining === 0 ? 'paid' : ($date < $today ? 'overdue' : ($paid > 0 ? 'partial' : ($date === $today ? 'due_today' : 'upcoming')));

                    return ['week' => (int) $number, 'due_on' => $date, 'amount' => $amount, 'paid' => $paid, 'remaining' => $remaining, 'status' => $status];
                })->values()->all();
            };
            $member = collect($loan->approved_snapshot['members'])->firstWhere('client_id', $clientId);
            $fees = app(LateFeeLedger::class)->state($loan, $rows);
            $personalFees = collect($fees['charges'])->where('client_id', $clientId)->map(fn ($f) => [
                'charged_on' => $f['charged_on'], 'base' => CreditCalculator::scaled($f['base']),
                'amount' => CreditCalculator::scaled($f['amount']), 'paid' => CreditCalculator::scaled($f['paid']),
                'balance' => CreditCalculator::scaled($f['balance']),
            ])->values();

            return ['active' => true, 'demo' => false, 'as_of' => $today, 'folio' => $loan->folio, 'group' => $loan->team?->name,
                'terms' => ['requested' => CreditCalculator::scaled($member['amount_requested']),
                    'approved' => CreditCalculator::scaled($member['amount_approved']),
                    'interest' => CreditCalculator::scaled($member['estimate']['interest']),
                    'guarantee_retained' => CreditCalculator::scaled($member['estimate']['guarantee']),
                    'net_delivered' => CreditCalculator::scaled($member['estimate']['net_delivery']),
                    'repayment_method' => $loan->repayment_method,
                    'rate' => $loan->approved_snapshot['rate']['name']],
                'late_fees' => ['enabled' => $fees['enabled'], 'balance' => $personalFees->sum('balance'), 'charges' => $personalFees],
                'personal' => $make($rows->where('client_id', $clientId)), 'group_schedule' => $make($rows)];
        }, 3);

        return response()->json($data)->header('Cache-Control', 'private, no-store');
    }
}
