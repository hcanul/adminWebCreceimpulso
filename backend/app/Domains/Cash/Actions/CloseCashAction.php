<?php

namespace App\Domains\Cash\Actions;

use App\Domains\Cash\DTOs\CloseCashData;
use App\Domains\Cash\Services\CashClosings;
use App\Domains\Credit\Services\CreditCalculator as Money;
use App\Domains\Identity\Services\FolioSequence;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CloseCashAction
{
    public function execute(User $actor, CloseCashData $data): string
    {
        $service = app(CashClosings::class);
        $service->authorize($actor);
        $service->authorize($actor, 'cash.close');
        $v = $data->values;
        $service->authorizeBranch($actor, $v['branch_id']);
        try {
            return DB::transaction(function () use ($actor, $data, $v, $service) {
                $currentActor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                $service->authorize($currentActor, 'cash.close');
                $service->authorizeBranch($currentActor, $v['branch_id']);
                $existing = DB::table('cash_closings')->where('id', $v['id'])->first();
                if ($existing) {
                    abort_unless($existing->actor_id === $actor->id && hash_equals($existing->request_hash, $data->fingerprint()), 409, 'El identificador corresponde a otro corte.');

                    return $existing->id;
                }
                $rows = $service->pending($actor, $v['branch_id'], $v['business_date'])->orderBy('p.id')->lockForUpdate()->get(['p.id', 'p.method', 'p.amount']);
                abort_if($rows->isEmpty(), 409, 'No hay cobros pendientes para este día y sucursal.');
                $totals = $service->summary($rows);
                abort_unless(hash_equals($totals['version'], $v['version']), 409, 'Los cobros pendientes cambiaron. Actualiza y revisa el corte.');
                $difference = Money::scaled($v['cash_counted']) - Money::scaled($totals['cash_total']);
                if ($difference !== 0 && ! $v['notes']) {
                    throw ValidationException::withMessages(['notes' => 'Explica la diferencia entre el efectivo recibido y contado.']);
                }
                $branch = DB::table('branches')->where('id', $v['branch_id'])->first();
                DB::table('cash_closings')->insert([
                    'id' => $v['id'], 'folio' => app(FolioSequence::class)->issue($v['branch_id'], 'closing'),
                    'branch_id' => $v['branch_id'], 'branch_name' => $branch->name,
                    'actor_id' => $actor->id, 'actor_name' => $actor->name, 'business_date' => $v['business_date'],
                    'cash_total' => $totals['cash_total'], 'bank_total' => $totals['bank_total'], 'cash_counted' => $v['cash_counted'],
                    'cash_difference' => ($difference < 0 ? '-' : '').Money::money(abs($difference)),
                    'payment_count' => $totals['payment_count'], 'notes' => $v['notes'], 'request_hash' => $data->fingerprint(), 'created_at' => now(),
                ]);
                foreach ($rows as $row) {
                    DB::table('cash_closing_payments')->insert(['id' => (string) Str::uuid(), 'cash_closing_id' => $v['id'], 'credit_payment_id' => $row->id]);
                }

                return $v['id'];
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            abort(409, 'El corte o uno de sus cobros ya se cerró. Actualiza antes de reintentar.');
        }
    }
}
