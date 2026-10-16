<?php

namespace App\Domains\Credit\Actions;

use App\Domains\Credit\DTOs\CreateCreditData;
use App\Domains\Credit\Services\CreditAccess;
use App\Domains\Credit\Services\CreditCalculator;
use App\Domains\Credit\Services\CreditEligibility;
use App\Domains\Intake\Services\IntakeAccess;
use App\Models\Client;
use App\Models\LoanApplication;
use App\Models\LoanParticipation;
use App\Models\Rate;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateCreditAction
{
    public function execute(User $actor, CreateCreditData $data): LoanApplication
    {
        app(CreditAccess::class)->authorize($actor, 'credit.create');
        app(IntakeAccess::class)->team($actor, $data->teamId);
        try {
            return DB::transaction(function () use ($actor, $data) {
                $team = Team::whereKey($data->teamId)->lockForUpdate()->firstOrFail();
                $existing = LoanApplication::withTrashed()->lockForUpdate()->find($data->id);
                if ($existing) {
                    abort_unless(! $existing->trashed() && $existing->created_by === $actor->id && $existing->request_hash === $data->fingerprint(), 409, 'Este identificador ya pertenece a otra solicitud.');

                    return $existing;
                }
                // Lock clients in stable order: simultaneous requests in different groups cannot double-enroll them.
                Client::withTrashed()->whereIn('id', array_column($data->participants, 'client_id'))->orderBy('id')->lockForUpdate()->get();
                $team->load(['branch', 'promoter', 'memberships.client']);
                $context = app(CreditEligibility::class)->forTeam($team);
                $errors = $context['issues'];
                $members = $context['members']->keyBy('client_id');
                $selected = [];
                foreach ($data->participants as $participant) {
                    $member = $members->get($participant['client_id']);
                    if (! $member) {
                        $errors[] = 'Un participante no pertenece al grupo.';
                    } elseif (! $member['eligible']) {
                        $errors[] = $member['name'].': '.implode(' ', $member['issues']);
                    } else {
                        $selected[] = $member + $participant;
                    }
                }
                foreach (['presidenta', 'tesorera'] as $role) {
                    if (count(array_filter($selected, fn ($row) => $row['role_in_group'] === $role)) !== 1) {
                        $errors[] = 'Selecciona exactamente una '.$role.' entre los participantes.';
                    }
                }
                if (count($selected) < 3) {
                    $errors[] = 'Se requieren al menos tres integrantes elegibles.';
                }
                if ($errors) {
                    throw ValidationException::withMessages(['participants' => $errors]);
                }
                $rate = Rate::where('is_active', true)->where('calculation_method', 'weekly_per_thousand')->lockForUpdate()->findOrFail($data->rateId);
                abort_unless(hash_equals($rate->terms_hash, $data->rateSignature), 409, 'La tasa cambió. Actualiza la selección y revisa los importes antes de guardar.');
                $limit = CreditCalculator::scaled($data->memberLimit);
                if ($limit <= 0) {
                    throw ValidationException::withMessages(['member_limit' => 'El límite por integrante debe ser mayor a cero.']);
                }
                $min = $rate->min_amount !== null ? CreditCalculator::scaled($rate->min_amount) : 1;
                $max = $rate->max_amount !== null ? min($limit, CreditCalculator::scaled($rate->max_amount)) : $limit;
                $totals = array_fill_keys(['principal', 'weekly_payment', 'total_repayment', 'interest', 'guarantee', 'net_delivery'], 0);
                $rows = [];
                foreach ($selected as $member) {
                    $cents = CreditCalculator::scaled($member['amount_requested']);
                    if ($cents < $min || $cents > $max) {
                        throw ValidationException::withMessages(['participants' => $member['name'].': el monto debe estar entre $'.CreditCalculator::money($min).' y $'.CreditCalculator::money($max).'.']);
                    }
                    $estimate = app(CreditCalculator::class)->estimate($cents, $rate->rate_factor);
                    foreach ($estimate as $key => $value) {
                        $totals[$key] += CreditCalculator::scaled($value);
                    }
                    $rows[] = ['client_id' => $member['client_id'], 'folio' => $member['folio'], 'name' => $member['name'], 'role_in_group' => $member['role_in_group'], 'amount_requested' => $member['amount_requested'], 'estimate' => $estimate];
                }
                if ($totals['principal'] > 999999999999) {
                    throw ValidationException::withMessages(['participants' => 'El total excede la capacidad del crédito.']);
                }
                $snapshot = ['weeks' => 16, 'guarantee_percent' => 10, 'member_limit' => $data->memberLimit, 'rate' => ['id' => $rate->id, 'name' => $rate->name, 'factor' => $rate->rate_factor, 'method' => $rate->calculation_method, 'min_amount' => $rate->min_amount, 'max_amount' => $rate->max_amount], 'members' => $rows, 'totals' => array_map(fn ($c) => CreditCalculator::money($c), $totals), 'team_name' => $team->name, 'branch_name' => $team->branch->name];
                $loan = new LoanApplication;
                $loan->forceFill(['id' => $data->id, 'branch_id' => $team->branch_id, 'team_id' => $team->id, 'promoter_id' => $team->promoter_id, 'rate_id' => $rate->id, 'created_by' => $actor->id, 'total_requested' => $snapshot['totals']['principal'], 'total_approved' => null, 'status' => 'pendiente', 'request_snapshot' => $snapshot, 'request_hash' => $data->fingerprint(), 'requested_at' => now(), 'disbursement_method' => null, 'repayment_method' => null])->save();
                foreach ($rows as $row) {
                    LoanParticipation::create(['branch_id' => $team->branch_id, 'loan_application_id' => $loan->id, 'client_id' => $row['client_id'], 'role_in_group' => $row['role_in_group'], 'amount_requested' => $row['amount_requested'], 'amount_approved' => null]);
                }

                return $loan;
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            abort(409, 'La solicitud se registró simultáneamente. Consulta el listado antes de reintentar.');
        }
    }
}
