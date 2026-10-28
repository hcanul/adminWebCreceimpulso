<?php

namespace App\Domains\Credit\Services;

use App\Domains\Credit\DTOs\ProposeCreditData;
use App\Domains\LateFees\Services\LateFeeLedger;
use App\Models\Client;
use App\Models\LoanApplication;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreditAuthorization
{
    public function payload(User $actor, LoanApplication $loan): array
    {
        $this->authorizeRead($actor);

        return [
            'status' => $loan->authorization_status, 'version' => $loan->authorization_version,
            'proposal' => $loan->authorization_proposal,
            'late_fee_policy' => $loan->approved_at ? ($loan->approved_snapshot['late_fee_policy'] ?? null) : (config('late_fees.new_authorizations') ? LateFeeLedger::policy() : null),
            'history' => DB::table('credit_authorization_events')->where('loan_application_id', $loan->id)->orderByDesc('version')->get()->map(function ($row) {
                $row->proposal = json_decode($row->proposal, true);

                return $row;
            }),
        ];
    }

    public function authorizeRead(User $actor): void
    {
        app(CreditAccess::class)->authorize($actor);
        abort_unless($actor->hasRole('Super Admin') || $actor->can('credit.propose') || $actor->can('credit.authorize'), 403);
    }

    public function lock(LoanApplication $visible, int $version): LoanApplication
    {
        Team::whereKey($visible->team_id)->lockForUpdate()->firstOrFail();
        $loan = LoanApplication::lockForUpdate()->findOrFail($visible->id);
        abort_unless($loan->status === 'pendiente' && $loan->request_snapshot && $loan->review_status === 'ready_for_authorization', 409, 'La solicitud debe estar pendiente y haber completado Gestión.');
        abort_unless($loan->authorization_version === $version, 409, 'Las condiciones cambiaron. Recarga antes de continuar.');

        return $loan;
    }

    public function validateEligibility(LoanApplication $loan): void
    {
        $ids = array_column($loan->request_snapshot['members'], 'client_id');
        Client::withTrashed()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        $team = Team::with(['branch', 'promoter', 'memberships.client'])->findOrFail($loan->team_id);
        $context = app(CreditEligibility::class)->forTeam($team, $loan->id);
        $issues = $context['issues'];
        $members = $context['members']->keyBy('client_id');
        foreach ($loan->request_snapshot['members'] as $original) {
            $current = $members->get($original['client_id']);
            if (! $current || ! $current['eligible'] || $current['role_in_group'] !== $original['role_in_group']) {
                $issues[] = $original['name'].': revisa su elegibilidad, pertenencia y cargo en el grupo.';
            }
        }
        if ($team->promoter_id !== $loan->promoter_id) {
            $issues[] = 'El promotor del grupo cambió desde la solicitud.';
        }
        $files = app(CreditReview::class)->files($loan, true);
        $event = DB::table('credit_review_events')->where('loan_application_id', $loan->id)->where('version', $loan->review_version)->where('action', 'complete')->first();
        $reviewedFiles = collect($event ? json_decode($event->files, true) : [])->keyBy('id');
        if ($files->count() !== count($ids) || ! $event) {
            $issues[] = 'Falta la revisión completa de los expedientes.';
        }
        foreach ($files as $file) {
            $reviewed = $reviewedFiles->get($file->id);
            if (! $reviewed || $reviewed['version'] !== $file->version || $reviewed['fields'] !== $file->fields || $file->status !== 'en_revision') {
                $issues[] = 'Un expediente cambió después de la revisión de Gestión.';
            }
        }
        if ($issues) {
            throw ValidationException::withMessages(['eligibility' => array_values(array_unique($issues))]);
        }
    }

    public function calculate(LoanApplication $loan, ProposeCreditData $data): array
    {
        $original = $loan->request_snapshot;
        $expected = array_column($original['members'], 'client_id');
        $actual = array_column($data->participants, 'client_id');
        sort($expected);
        sort($actual);
        if ($expected !== $actual) {
            throw ValidationException::withMessages(['participants' => 'La propuesta debe incluir exactamente los integrantes de la solicitud.']);
        }
        $inputs = collect($data->participants)->keyBy('client_id');
        $rows = [];
        $totals = array_fill_keys(['principal', 'weekly_payment', 'total_repayment', 'interest', 'guarantee', 'net_delivery'], 0);
        foreach ($original['members'] as $member) {
            $amount = CreditCalculator::scaled($inputs[$member['client_id']]['amount_proposed']);
            $min = isset($original['rate']['min_amount']) ? CreditCalculator::scaled($original['rate']['min_amount']) : 1;
            $max = min(CreditCalculator::scaled($member['amount_requested']), CreditCalculator::scaled($original['member_limit']));
            if (isset($original['rate']['max_amount'])) {
                $max = min($max, CreditCalculator::scaled($original['rate']['max_amount']));
            }
            if ($amount < $min || $amount > $max) {
                throw ValidationException::withMessages(['participants' => $member['name'].': el importe debe estar entre $'.CreditCalculator::money($min).' y $'.CreditCalculator::money($max).', sin exceder lo solicitado.']);
            }
            $estimate = app(CreditCalculator::class)->estimate($amount, $original['rate']['factor']);
            foreach ($estimate as $key => $value) {
                $totals[$key] += CreditCalculator::scaled($value);
            }
            $rows[] = ['client_id' => $member['client_id'], 'name' => $member['name'], 'folio' => $member['folio'], 'role_in_group' => $member['role_in_group'], 'amount_requested' => $member['amount_requested'], 'amount_proposed' => CreditCalculator::money($amount), 'estimate' => $estimate];
        }

        return ['review_version' => $loan->review_version, 'weeks' => 16, 'guarantee_percent' => 10, 'rate' => $original['rate'], 'members' => $rows, 'totals' => array_map(fn ($value) => CreditCalculator::money($value), $totals), 'disbursement_method' => $data->disbursementMethod, 'repayment_method' => $data->repaymentMethod];
    }

    public function record(User $actor, LoanApplication $loan, string $action, string $before, string $notes): void
    {
        DB::table('credit_authorization_events')->insert([
            'id' => (string) Str::uuid(), 'loan_application_id' => $loan->id, 'branch_id' => $loan->branch_id,
            'actor_id' => $actor->id, 'actor_name' => $actor->name, 'version' => $loan->authorization_version,
            'action' => $action, 'from_status' => $before, 'to_status' => $loan->authorization_status,
            'notes' => $notes, 'proposal' => json_encode($action === 'approve' ? array_merge($loan->authorization_proposal, ['first_due_on' => $loan->approved_snapshot['first_due_on'] ?? null]) : $loan->authorization_proposal, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }
}
