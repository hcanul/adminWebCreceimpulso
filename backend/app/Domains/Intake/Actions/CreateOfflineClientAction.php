<?php

namespace App\Domains\Intake\Actions;

use App\Domains\Intake\DTOs\SaveApplicationData;
use App\Domains\Intake\Services\IntakeAccess;
use App\Models\ClientApplication;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateOfflineClientAction
{
    public function execute(User $actor, SaveApplicationData $dto, string $proposed): ClientApplication
    {
        abort_unless(! $dto->submit && $dto->version === 0, 422, 'El alta offline solo crea borradores.');
        $data = $dto->toArray();
        ksort($data['fields']);
        ksort($data);
        $hash = hash('sha256', json_encode([$proposed, $data], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $dto, $proposed, $hash) {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            Team::whereKey($dto->teamId)->lockForUpdate()->firstOrFail();
            $access = app(IntakeAccess::class);
            $team = $access->team($actor, $dto->teamId);
            abort_unless($team->is_active && $team->branch?->is_active, 422);
            $prior = DB::table('offline_client_creations')->where('id', $dto->id)->first();
            if ($prior) {
                abort_unless($prior->actor_id === $actor->id && hash_equals($prior->request_hash, $hash), 409, 'El identificador corresponde a otra captura.');
                $application = ClientApplication::with('client')->findOrFail($dto->id);
                abort_unless($application->client && $access->client($actor, $application->client), 403);
                abort_unless($application->status === 'borrador' && $application->version === $prior->application_version && $application->client->version === $prior->client_version, 409, 'El alta ya fue aplicada y oficina hizo cambios posteriores. Revisa el expediente.');

                return $application;
            }
            $saved = app(SaveApplicationAction::class)->execute($actor, $dto, $proposed);
            DB::table('offline_client_creations')->insert(['id' => $saved->id, 'actor_id' => $actor->id, 'proposed_client_id' => $proposed, 'client_id' => $saved->client_id, 'request_hash' => $hash, 'application_version' => $saved->version, 'client_version' => $saved->client->version, 'created_at' => now()]);

            return $saved;
        }, 3);
    }
}
