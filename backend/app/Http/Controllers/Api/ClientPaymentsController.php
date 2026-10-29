<?php

namespace App\Http\Controllers\Api;

use App\Domains\Credit\Services\CreditCalculator as Money;
use App\Domains\LateFees\Services\LateFeeLedger;
use App\Domains\Tickets\Services\PaymentReceipt;
use App\Http\Controllers\Controller;
use App\Models\LoanApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

final class ClientPaymentsController extends Controller
{
    public function __invoke(Request $request)
    {
        $validated = $request->validate(['page' => ['sometimes', 'integer', 'min:1', 'max:10000']]);
        $clientId = $request->user()->client_id;
        $data = DB::transaction(function () use ($clientId, $validated) {
            $loans = LoanApplication::where('status', 'entregado')
                ->whereHas('participations', fn ($q) => $q->where('client_id', $clientId))->lockForUpdate()->get();
            abort_if($loans->count() > 1, 409, 'Hay varios créditos activos. Consulta en sucursal.');
            $loan = $loans->first();
            if (! $loan) return ['receipts' => [], 'has_more' => false];
            $page = DB::table('credit_payments as p')->where('p.loan_application_id', $loan->id)
                ->where(function ($q) use ($clientId) {
                    $q->whereExists(fn ($s) => $s->selectRaw('1')->from('credit_payment_allocations as a')
                        ->join('loan_installments as i', 'i.id', '=', 'a.loan_installment_id')
                        ->whereColumn('a.credit_payment_id', 'p.id')
                        ->where(fn ($w) => $w->where('i.client_id', $clientId)->orWhere('a.payer_client_id', $clientId)))
                      ->orWhereExists(fn ($s) => $s->selectRaw('1')->from('late_fee_payment_allocations as a')
                        ->join('weekly_late_fees as f', 'f.id', '=', 'a.weekly_late_fee_id')
                        ->whereColumn('a.credit_payment_id', 'p.id')
                        ->where(fn ($w) => $w->where('f.client_id', $clientId)->orWhere('a.payer_client_id', $clientId)));
                })->orderByDesc('p.received_on')->orderByDesc('p.number')->simplePaginate(20, ['p.id'], 'page', $validated['page'] ?? 1);
            $receipts = collect($page->items())->map(function ($payment) use ($clientId) {
                $data = app(PaymentReceipt::class)->payload($payment->id);
                $parts = DB::table('credit_payment_allocations as a')->join('loan_installments as i', 'i.id', '=', 'a.loan_installment_id')
                    ->where('a.credit_payment_id', $payment->id)->get(['i.client_id', 'a.payer_client_id', 'a.amount']);
                $parts = $parts->concat(app(LateFeeLedger::class)->allocations([$payment->id]));
                return $data + [
                    'personal_applied' => Money::money($parts->where('client_id', $clientId)->sum(fn ($r) => Money::scaled((string) $r->amount))),
                    'personal_contributed' => Money::money($parts->where('payer_client_id', $clientId)->sum(fn ($r) => Money::scaled((string) $r->amount))),
                    'verification_url' => URL::signedRoute('receipts.verify', ['id' => $payment->id]),
                ];
            })->values();
            return ['receipts' => $receipts, 'has_more' => $page->hasMorePages()];
        }, 3);
        return response()->json($data)->header('Cache-Control', 'no-store, private');
    }
}
