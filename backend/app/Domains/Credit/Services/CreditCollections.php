<?php

namespace App\Domains\Credit\Services;

use App\Models\LoanApplication;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CreditCollections
{
    public static function authorizeRead(User $actor): void
    {
        app(CreditAccess::class)->authorize($actor, $actor->can('credit.reverse') ? 'credit.reverse' : 'credit.collect');
    }

    public function version(Collection $rows): string
    {
        return hash('sha256', $rows->map(fn ($r) => [$r->id, (string) $r->amount_due, (string) $r->amount_paid])->toJson());
    }

    public function rows(string $id, bool $lock = false): Collection
    {
        $query = DB::table('loan_installments')->where('loan_application_id', $id)->orderBy('due_on')->orderBy('number')->orderBy('id');

        return ($lock ? $query->lockForUpdate() : $query)->get();
    }

    public function memberBalances(Collection $rows, array $members, string $today): Collection
    {
        return collect($members)->map(function ($m) use ($rows, $today) {
            $balance = $overdue = $due = 0;
            $oldest = null;
            foreach ($rows->where('client_id', $m['client_id']) as $r) {
                $left = CreditCalculator::scaled((string) $r->amount_due) - CreditCalculator::scaled((string) $r->amount_paid);
                $balance += $left;
                if ($r->due_on <= $today) {
                    $due += $left;
                }
                if ($left > 0 && $r->due_on < $today) {
                    $overdue += $left;
                    $oldest ??= $r->due_on;
                }
            }

            return ['client_id' => $m['client_id'], 'name' => $m['name'], 'folio' => $m['folio'], 'balance' => CreditCalculator::money($balance), 'due' => CreditCalculator::money($due), 'overdue' => CreditCalculator::money($overdue), 'days_overdue' => $oldest ? (int) CarbonImmutable::parse($oldest)->diffInDays(CarbonImmutable::parse($today)) : 0];
        });
    }

    public function payload(User $actor, LoanApplication $loan, int $page = 1): array
    {
        self::authorizeRead($actor);

        // A consistent snapshot prevents mixing a concurrent payment with old installment balances.
        return DB::transaction(function () use ($loan, $page) {
            $locked = LoanApplication::whereKey($loan->id)->lockForUpdate()->firstOrFail();
            $rows = $this->rows($loan->id);
            $today = now('America/Cancun')->toDateString();
            $members = $this->memberBalances($rows, $locked->approved_snapshot['members'] ?? [], $today);
            $payments = DB::table('credit_payments')->where('loan_application_id', $loan->id)->orderByDesc('number')->paginate(20, ['id', 'actor_name', 'received_on', 'method', 'amount', 'bank_name', 'bank_reference', 'deposited_on', 'notes', 'created_at'], 'page', $page);
            $allocations = DB::table('credit_payment_allocations as a')->join('loan_installments as i', 'i.id', '=', 'a.loan_installment_id')->whereIn('a.credit_payment_id', collect($payments->items())->pluck('id'))->get(['a.credit_payment_id', 'a.payer_client_id', 'a.amount', 'i.client_id', 'i.number', 'i.due_on'])->groupBy('credit_payment_id');
            $reversals = DB::table('credit_payment_reversals')->whereIn('credit_payment_id', collect($payments->items())->pluck('id'))->get(['credit_payment_id', 'id', 'actor_name', 'reason', 'reversed_on'])->keyBy('credit_payment_id');
            $payments->through(function ($p) use ($allocations, $reversals) {
                $p->reversal = $reversals[$p->id] ?? null;
                $p->amount = CreditCalculator::money(CreditCalculator::scaled((string) $p->amount));
                $p->allocations = ($allocations[$p->id] ?? collect())->map(function ($a) {
                    $a->amount = CreditCalculator::money(CreditCalculator::scaled((string) $a->amount));

                    return $a;
                });

                return $p;
            });

            return ['balance_version' => $this->version($rows), 'status' => $locked->status, 'as_of' => $today, 'members' => $members, 'payments' => $payments];
        });
    }
}
