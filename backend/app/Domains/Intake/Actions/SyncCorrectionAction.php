<?php

namespace App\Domains\Intake\Actions;

use App\Domains\Intake\DTOs\SaveApplicationData;
use App\Domains\Intake\Services\IntakeAccess;
use App\Models\ClientApplication;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SyncCorrectionAction
{
    public function execute(User $actor, SaveApplicationData $dto, string $operation): ClientApplication
    {
        abort_unless(! $dto->submit && $dto->version > 0 && $dto->clientId, 422, 'Solo correcciones de borradores existentes.');
        $data = $dto->toArray();
        ksort($data['fields']);
        ksort($data);
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $dto, $operation, $hash) {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            // Acquire the same group lock as office edits before opening a MySQL read snapshot.
            Team::whereKey($dto->teamId)->lockForUpdate()->firstOrFail();
            $access = app(IntakeAccess::class);
            $team = $access->team($actor, $dto->teamId);
            abort_unless($team->is_active && $team->branch?->is_active, 422);
            $application = ClientApplication::whereKey($dto->id)->where('team_id', $team->id)->where('client_id', $dto->clientId)->with('client')->firstOrFail();
            abort_unless($application->client && $access->client($actor, $application->client), 403);
            $prior = DB::table('offline_correction_operations')->where('id', $operation)->first();
            if ($prior) {
                abort_unless($prior->actor_id === $actor->id && $prior->application_id === $dto->id && hash_equals($prior->request_hash, $hash), 409, 'La operación corresponde a otra corrección.');
                abort_unless($application->status === 'borrador' && $application->version === $prior->application_version && $application->client->version === $prior->client_version, 409, 'La corrección ya fue aplicada, pero oficina realizó cambios posteriores. Revisa la versión actual.');

                return $application;
            }
            $saved = app(SaveApplicationAction::class)->execute($actor, $dto);
            DB::table('offline_correction_operations')->insert(['id' => $operation, 'actor_id' => $actor->id, 'application_id' => $saved->id, 'request_hash' => $hash, 'application_version' => $saved->version, 'client_version' => $saved->client->version, 'created_at' => now()]);

            return $saved;
        }, 3);
    }
}
