<?php

namespace App\Domains\Intake\Actions;

use App\Domains\Intake\DTOs\SaveApplicationData;
use App\Domains\Intake\Services\ApplicationFields;
use App\Domains\Intake\Services\IntakeAccess;
use App\Models\ApplicationRevision;
use App\Models\Client;
use App\Models\ClientApplication;
use App\Models\Promoter;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class SaveApplicationAction
{
    public function execute(User $actor, SaveApplicationData $dto): ClientApplication
    {
        $data = $dto->toArray();
        abort_if($data['submit'] && ! ($actor->hasRole('Super Admin') || $actor->can('intake.submit')), 403, 'El gerente debe enviar el expediente a Gestión.');
        $access = app(IntakeAccess::class);
        $team = $access->team($actor, $data['team_id']);
        abort_unless($team->is_active && $team->branch?->is_active, 422, 'El grupo o la sucursal no están activos.');
        abort_unless($team->promoter_id && Promoter::assignable()->whereKey($team->promoter_id)->where('branch_id', $team->branch_id)->exists(), 422, 'Asigna un promotor válido al grupo antes de capturar expedientes.');
        $fields = $data['fields'];
        unset($fields['ncliente'], $fields['nCredito']);
        $fields['city_id'] = $team->branch_id;
        $fields['team_id'] = $team->id;
        $fields['promoter_id'] = $team->promoter_id;
        $fields['curp'] = $data['curp'];
        $fields = app(ApplicationFields::class)->validate($fields, $data['submit']);
        try {
            return DB::transaction(function () use ($actor, $data, $access, $team, $fields) {
                Team::whereKey($team->id)->lockForUpdate()->firstOrFail();
                $client = Client::withTrashed()->where('curp', $data['curp'])->lockForUpdate()->first();
                if ($client) {
                    abort_unless($access->client($actor, $client), 403, 'El expediente no puede modificarse con este perfil.');
                    abort_unless(($data['client_id'] ?? null) === $client->id && ($data['confirm_existing'] ?? false), 409, 'La CURP ya existe. Consulta el expediente y confirma la edición.');
                    abort_unless(($data['client_version'] ?? null) === $client->version, 409, 'El cliente cambió. Consulta nuevamente la CURP antes de guardar.');
                } else {
                    abort_if(! empty($data['client_id']), 409, 'La identidad cambió. Consulta nuevamente la CURP.');
                    $client = new Client(['branch_id' => $team->branch_id, 'curp' => $data['curp'], 'version' => 0]);
                }
                $application = ClientApplication::whereKey($data['id'])->lockForUpdate()->first();
                if ($application) {
                    abort_unless($application->team_id === $team->id && $application->client_id === $client->id, 409);
                    abort_unless($application->status === 'borrador', 409, 'El expediente enviado conserva su versión original y no puede sobrescribirse.');
                    abort_unless($application->version === $data['version'], 409, 'Otra persona modificó el expediente. Vuelve a abrirla.');
                } else {
                    abort_unless($data['version'] === 0, 409);
                    abort_if($client->exists && ClientApplication::where('team_id', $team->id)->where('client_id', $client->id)->whereIn('status', ['borrador', 'en_revision'])->exists(), 409, 'Ya existe un expediente abierto de esta persona en el grupo.');
                    $application = new ClientApplication(['branch_id' => $team->branch_id, 'team_id' => $team->id, 'created_by' => $actor->id, 'version' => 0]);
                    $application->id = $data['id'];
                }
                $client->fill(['first_name' => trim($fields['pNombre'].' '.($fields['sNombre'] ?? '')), 'last_name' => trim($fields['aPaterno'].' '.($fields['aMaterno'] ?? '')), 'phone' => $fields['dudtelefono'] ?? null, 'address' => $fields['dudcalle'] ?? null, 'version' => $client->version + 1]);
                $client->save();
                $fields['ncliente'] = $client->folio;
                // Credit identifiers belong to the later credit workflow; preserve legacy snapshots only.
                $fields['nCredito'] = $application->exists ? ($application->fields['nCredito'] ?? null) : null;
                TeamMembership::firstOrCreate(['team_id' => $team->id, 'client_id' => $client->id], ['branch_id' => $team->branch_id]);
                $application->fill(['client_id' => $client->id, 'fields' => $fields, 'version' => $application->version + 1, 'status' => $data['submit'] ? 'en_revision' : 'borrador', 'submitted_at' => $data['submit'] ? now() : null]);
                $application->save();
                ApplicationRevision::create(['application_id' => $application->id, 'actor_id' => $actor->id, 'version' => $application->version, 'snapshot' => $application->only(['fields', 'status'])]);

                return $application->load('client');
            });
        } catch (UniqueConstraintViolationException $e) {
            abort(409, 'El registro se creó simultáneamente. Consulta nuevamente la CURP para evitar duplicados.');
        }
    }
}
