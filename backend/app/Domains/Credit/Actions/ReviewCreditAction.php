<?php

namespace App\Domains\Credit\Actions;

use App\Domains\Credit\DTOs\ReviewCreditData;
use App\Domains\Credit\Enums\ReviewStatus;
use App\Domains\Credit\Services\CreditAccess;
use App\Domains\Credit\Services\CreditReview;
use App\Domains\Intake\Services\IntakeAccess;
use App\Models\ApplicationRevision;
use App\Models\LoanApplication;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ReviewCreditAction
{
    public function execute(User $actor, string $id, ReviewCreditData $data): LoanApplication
    {
        $access = app(CreditAccess::class);
        $access->authorize($actor, $data->action === 'resubmit' ? 'credit.create' : 'credit.review');
        $visible = $access->query($actor)->findOrFail($id);
        if ($data->action === 'resubmit') {
            app(IntakeAccess::class)->team($actor, $visible->team_id);
        }

        return DB::transaction(function () use ($actor, $id, $visible, $data) {
            // Same lock order as dossier edits: group first, then request and files.
            $team = Team::whereKey($visible->team_id)->lockForUpdate()->firstOrFail();
            $loan = LoanApplication::lockForUpdate()->findOrFail($id);
            abort_unless($team->is_active && $team->branch?->is_active, 422, 'El grupo o la sucursal no están activos.');
            abort_unless($loan->status === 'pendiente' && $loan->request_snapshot, 409, 'Solo se revisan solicitudes pendientes con condiciones conservadas.');
            abort_unless($loan->review_version === $data->version, 409, 'La revisión cambió. Recarga antes de continuar.');
            $before = $loan->review_status;
            $files = app(CreditReview::class)->files($loan, true);
            $members = array_column($loan->request_snapshot['members'], 'client_id');
            abort_unless($files->count() === count($members), 422, 'Faltan expedientes de integrantes.');
            if ($data->action === 'resubmit') {
                abort_unless($before === ReviewStatus::Returned->value, 409, 'Solo se reenvían solicitudes devueltas.');
                abort_unless($files->every(fn ($file) => $file->status === 'en_revision'), 422, 'Envía nuevamente todos los expedientes corregidos desde Clientes.');
                $checks = [];
                $next = ReviewStatus::Pending;
            } else {
                abort_unless(in_array($before, [ReviewStatus::Pending->value, ReviewStatus::InReview->value]), 409, 'Esta revisión ya fue cerrada o está pendiente de corrección.');
                abort_unless($files->every(fn ($file) => $file->status === 'en_revision'), 422, 'Todos los expedientes deben estar enviados.');
                $checks = $data->checks;
                $expected = [];
                foreach ($members as $member) {
                    foreach (CreditReview::CHECKS as $key) {
                        $expected[] = $member.':'.$key;
                    }
                }
                $actual = array_map(fn ($check) => $check['client_id'].':'.$check['key'], $checks);
                sort($expected);
                sort($actual);
                if ($actual !== $expected) {
                    throw ValidationException::withMessages(['checks' => 'Incluye exactamente las seis comprobaciones de cada integrante.']);
                }
                $observed = array_filter($checks, fn ($check) => $check['status'] === 'observed');
                if ($data->action === 'return' && ! $observed) {
                    throw ValidationException::withMessages(['checks' => 'Marca al menos una comprobación con observaciones.']);
                }
                if ($data->action === 'complete' && count(array_filter($checks, fn ($check) => $check['status'] === 'verified')) !== count($expected)) {
                    throw ValidationException::withMessages(['checks' => 'Verifica todas las comprobaciones antes de enviar a autorización.']);
                }
                $next = match ($data->action) {
                    'return' => ReviewStatus::Returned, 'complete' => ReviewStatus::Ready, default => ReviewStatus::InReview
                };
            }
            $fileSnapshot = $files->map(fn ($file) => $file->only(['id', 'client_id', 'version', 'status', 'fields', 'submitted_at']))->all();
            if ($data->action === 'return') {
                $observedIds = array_column($observed, 'client_id');
                foreach ($files->whereIn('client_id', $observedIds) as $file) {
                    $file->forceFill(['status' => 'borrador', 'version' => $file->version + 1, 'submitted_at' => null])->save();
                    ApplicationRevision::create(['application_id' => $file->id, 'actor_id' => $actor->id, 'version' => $file->version, 'snapshot' => ['fields' => $file->fields, 'status' => 'borrador', 'reason' => 'credit_review_return', 'loan_application_id' => $loan->id]]);
                }
            }
            $loan->forceFill(['review_status' => $next->value, 'review_version' => $loan->review_version + 1, 'review_checks' => $checks])->save();
            DB::table('credit_review_events')->insert([
                'id' => (string) Str::uuid(), 'loan_application_id' => $loan->id, 'branch_id' => $loan->branch_id,
                'actor_id' => $actor->id, 'actor_name' => $actor->name, 'version' => $loan->review_version,
                'action' => $data->action, 'from_status' => $before, 'to_status' => $next->value,
                'notes' => $data->notes ?: null, 'checks' => json_encode($checks, JSON_THROW_ON_ERROR),
                'files' => json_encode($fileSnapshot, JSON_THROW_ON_ERROR), 'created_at' => now(),
            ]);

            return $loan;
        }, 3);
    }
}
