<?php

namespace App\Domains\Intake\Actions;

use App\Domains\Intake\Services\IntakeAccess;
use App\Models\Client;
use App\Models\ClientApplication;
use App\Models\LoanApplication;
use App\Models\User;

final class LookupClientAction
{
    public function execute(User $actor, string $curp, string $teamId): array
    {
        $access = app(IntakeAccess::class);
        $access->team($actor, $teamId);
        $client = Client::withTrashed()->with('branch:id,name')->where('curp', $curp)->first();
        if (! $client) {
            return ['found' => false];
        }
        $canEdit = $access->client($actor, $client);
        $loans = LoanApplication::with(['branch:id,name', 'team:id,name'])->whereHas('participations', fn ($q) => $q->where('client_id', $client->id));
        $summarize = fn ($loan) => $loan ? [
            'id' => $loan->id, 'folio' => $loan->folio, 'status' => $loan->status, 'branch' => $loan->branch?->name,
            'group' => $loan->team?->name, 'registered_at' => $loan->created_at?->toISOString(),
            'pacted_date' => $loan->pacted_date?->toDateString(),
        ] : null;
        $active = (clone $loans)->where('status', 'entregado')->latest()->get()->map($summarize)->all();
        $last = (clone $loans)->whereIn('status', ['entregado', 'pagado'])->latest()->first();
        $application = $canEdit ? ClientApplication::where('client_id', $client->id)->where('team_id', $teamId)->latest()->first() : null;

        $profile = null;
        if ($canEdit) {
            $previous = ClientApplication::where('client_id', $client->id)->whereIn('team_id', $access->teams($actor)->select('id'))->latest()->first();
            $profile = $previous?->fields ?? ['pNombre' => $client->first_name, 'aPaterno' => $client->last_name, 'curp' => $client->curp, 'dudtelefono' => $client->phone];
            foreach (['fecha', 'city_id', 'team_id', 'promoter_id', 'nCredito'] as $key) {
                unset($profile[$key]);
            }
        }

        return [
            'found' => true, 'can_edit' => $canEdit,
            'client' => ['id' => $client->id, 'folio' => $client->folio, 'branch' => $client->branch?->name, 'status' => $client->trashed() ? 'archivado' : $client->status,
                'name' => $canEdit ? trim($client->first_name.' '.$client->last_name) : null, 'version' => $canEdit ? $client->version : null],
            'active_credits' => $active, 'last_credit' => $summarize($last),
            'application' => $application,
            'profile' => $profile,
            'message' => $canEdit ? 'La persona ya está registrada. ¿Deseas abrir su expediente?' : 'Registro existente. Solicita la intervención de la sucursal responsable; no se creará un duplicado.',
        ];
    }
}
