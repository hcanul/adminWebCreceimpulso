<?php

namespace App\Domains\Credit\Services;

use App\Domains\Intake\Services\IntakeAccess;
use App\Models\ClientApplication;
use App\Models\LoanApplication;
use App\Models\LoanParticipation;
use App\Models\Team;
use App\Models\User;

final class CreditEligibility
{
    public function context(User $actor, string $teamId): array
    {
        app(CreditAccess::class)->authorize($actor);
        $team = app(IntakeAccess::class)->team($actor, $teamId)->load(['branch:id,name,is_active', 'promoter:id,branch_id', 'memberships.client']);

        return $this->forTeam($team);
    }

    public function forTeam(Team $team, ?string $exceptLoanId = null): array
    {
        $blocked = LoanParticipation::whereIn('client_id', $team->memberships->pluck('client_id'))->whereHas('loan', fn ($q) => $q->whereIn('status', ['pendiente', 'aprobado', 'entregado'])->when($exceptLoanId, fn ($q) => $q->where('id', '!=', $exceptLoanId)))->pluck('client_id')->all();
        $files = ClientApplication::where('team_id', $team->id)->where('status', 'en_revision')->pluck('client_id')->all();
        $reasons = [];
        if (! $team->is_active || ! $team->branch?->is_active) {
            $reasons[] = 'El grupo o su sucursal no están activos.';
        }
        if (! $team->promoter()->where(fn ($q) => $q->assignable())->where('branch_id', $team->branch_id)->exists()) {
            $reasons[] = 'Asigna un promotor activo de la misma sucursal.';
        }
        if (LoanApplication::where('team_id', $team->id)->when($exceptLoanId, fn ($q) => $q->where('id', '!=', $exceptLoanId))->whereIn('status', ['pendiente', 'aprobado', 'entregado'])->exists()) {
            $reasons[] = 'El grupo ya tiene una solicitud o crédito vigente.';
        }
        if ($team->memberships->contains(fn ($member) => $member->client?->status === 'moroso')) {
            $reasons[] = 'El grupo tiene integrantes con mora registrada. Resuelve los atrasos antes de solicitar otro crédito.';
        }
        $members = $team->memberships->map(function ($membership) use ($blocked, $files) {
            $client = $membership->client;
            $issues = [];
            if (! $client || in_array($client->status, ['baja', 'moroso'])) {
                $issues[] = 'Cliente dado de baja, archivado o con mora registrada.';
            }
            if (in_array($membership->client_id, $blocked)) {
                $issues[] = 'Tiene otra solicitud o crédito pendiente, aprobado o activo.';
            }
            if (! in_array($membership->client_id, $files)) {
                $issues[] = 'Envía primero su expediente completo a Gestión.';
            }

            return ['client_id' => $membership->client_id, 'folio' => $client?->folio, 'name' => $client ? trim($client->first_name.' '.$client->last_name) : 'Cliente no disponible', 'role_in_group' => $membership->role_in_group, 'eligible' => ! $issues, 'issues' => $issues];
        })->values();

        return ['team' => ['id' => $team->id, 'name' => $team->name, 'branch' => $team->branch?->name, 'promoter' => $team->promoter?->name], 'issues' => $reasons, 'members' => $members];
    }
}
