<?php

namespace App\Domains\Credit\Actions;

use App\Domains\Credit\DTOs\DisburseCreditData;
use App\Domains\Credit\Services\CreditAccess;
use App\Domains\Credit\Services\CreditAuthorization;
use App\Domains\Credit\Services\CreditDisbursement;
use App\Models\LoanApplication;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DisburseCreditAction
{
    public function execute(User $actor, string $id, DisburseCreditData $data): LoanApplication
    {
        $access = app(CreditAccess::class);
        $access->authorize($actor, 'credit.disburse');
        $visible = $access->query($actor)->findOrFail($id);
        try {
            return DB::transaction(function () use ($actor, $visible, $data) {
                Team::whereKey($visible->team_id)->lockForUpdate()->firstOrFail();
                $loan = LoanApplication::lockForUpdate()->findOrFail($visible->id);
                $v = $data->values;
                $existing = DB::table('credit_disbursements')->where('id', $v['id'])->lockForUpdate()->first();
                if ($existing) {
                    abort_unless($existing->loan_application_id === $loan->id && $existing->actor_id === $actor->id && hash_equals($existing->request_hash, $data->fingerprint()), 409, 'El identificador ya corresponde a otra entrega.');

                    return $loan;
                }
                abort_unless($loan->status === 'aprobado' && $loan->authorization_status === 'authorized' && $loan->approved_at && $loan->approved_snapshot, 409, 'Solo se entregan créditos autorizados y todavía no desembolsados.');
                abort_unless($loan->authorization_version === $v['version'], 409, 'La autorización cambió. Recarga el crédito.');
                abort_if(DB::table('credit_disbursements')->where('loan_application_id', $loan->id)->lockForUpdate()->exists(), 409, 'Este crédito ya tiene una entrega registrada.');
                if ($v['disbursed_on'] < $loan->approved_at->timezone('America/Cancun')->toDateString()) {
                    throw ValidationException::withMessages(['disbursed_on' => 'La entrega no puede ser anterior a la autorización.']);
                }
                $agreedDate = $loan->approved_snapshot['first_due_on'] ?? null;
                if ($agreedDate !== null && $v['first_due_on'] !== $agreedDate) {
                    throw ValidationException::withMessages(['first_due_on' => 'El primer pago debe respetar la fecha pactada en la autorización: '.$agreedDate.'.']);
                }
                $bank = $loan->disbursement_method === 'bank_deposit';
                if ($bank && (! $v['bank_name'] || ! $v['bank_reference'])) {
                    throw ValidationException::withMessages(['bank_reference' => 'Captura el banco y la referencia de la entrega bancaria.']);
                }
                if (! $bank && ($v['bank_name'] || $v['bank_reference'])) {
                    throw ValidationException::withMessages(['bank_reference' => 'La entrega autorizada en sucursal no lleva referencia bancaria.']);
                }
                app(CreditAuthorization::class)->validateEligibility($loan);
                $snapshot = $loan->approved_snapshot;
                $parts = $loan->participations()->orderBy('client_id')->lockForUpdate()->get()->keyBy('client_id');
                abort_unless($parts->count() === count($snapshot['members']) && $snapshot['totals']['principal'] === $loan->total_approved, 409, 'Revisa la integridad de los importes autorizados.');
                foreach ($snapshot['members'] as $member) {
                    abort_unless(isset($parts[$member['client_id']]) && $parts[$member['client_id']]->amount_approved === $member['amount_approved'], 409, 'Las participaciones no coinciden con la autorización.');
                }
                $now = now();
                DB::table('credit_disbursements')->insert([
                    'id' => $v['id'], 'loan_application_id' => $loan->id, 'branch_id' => $loan->branch_id,
                    'actor_id' => $actor->id, 'actor_name' => $actor->name, 'disbursed_on' => $v['disbursed_on'], 'first_due_on' => $v['first_due_on'],
                    'method' => $loan->disbursement_method, 'bank_name' => $v['bank_name'], 'bank_reference' => $v['bank_reference'],
                    'capital' => $snapshot['totals']['principal'], 'guarantee_retained' => $snapshot['totals']['guarantee'], 'net_delivered' => $snapshot['totals']['net_delivery'],
                    'notes' => $v['notes'], 'request_hash' => $data->fingerprint(), 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'created_at' => $now,
                ]);
                foreach (app(CreditDisbursement::class)->schedule($snapshot, $v['first_due_on']) as $row) {
                    DB::table('loan_installments')->insert($row + ['id' => (string) Str::uuid(), 'branch_id' => $loan->branch_id, 'loan_application_id' => $loan->id, 'loan_participation_id' => $parts[$row['client_id']]->id, 'amount_paid' => '0.00', 'created_at' => $now]);
                }
                $loan->status = 'entregado';
                $loan->save();

                return $loan;
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            abort(409, 'La entrega o el calendario ya se registraron. Recarga antes de reintentar.');
        }
    }
}
