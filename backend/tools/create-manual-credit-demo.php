<?php

// Run only explicitly: php tools/create-manual-credit-demo.php
// Deliberately not included in DatabaseSeeder. Never resets an existing demo.

use App\Domains\Credit\Services\CreditEligibility;
use App\Domains\Intake\Actions\AssignCommitteeAction;
use App\Domains\Intake\Actions\SaveApplicationAction;
use App\Domains\Intake\DTOs\SaveApplicationData;
use App\Domains\Intake\Services\ApplicationFields;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Promoter;
use App\Models\Rate;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$c = config('database.connections.mysql');
if (! $app->environment('local') || config('database.default') !== 'mysql'
    || ! in_array($c['host'], ['localhost', '127.0.0.1', '::1'], true)
    || $c['database'] !== 'creceimpulso_db' || ! empty($c['url']) || ! empty($c['read']) || ! empty($c['write'])) {
    throw new RuntimeException('Solo se permite el MySQL local de desarrollo previsto.');
}
$directory = storage_path('app/private');
if (! is_dir($directory)) {
    mkdir($directory, 0700, true);
}
umask(0077);
$lock = fopen($directory.'/manual-credit-demo.lock', 'c');
if (! $lock || ! flock($lock, LOCK_EX)) {
    throw new RuntimeException('No fue posible bloquear la preparación del demo.');
}
$reportPath = $directory.'/manual-credit-demo.json';
if (is_file($reportPath)) {
    $saved = json_decode(file_get_contents($reportPath), true, flags: JSON_THROW_ON_ERROR);
    if (! Branch::withTrashed()->whereKey($saved['branch']['id'])->exists() || User::whereIn('id', array_column($saved['users'], 'id'))->count() !== count($saved['users'])) {
        throw new RuntimeException('El reporte demo existe pero faltan sus registros. Revisar manualmente; no se reinicia automáticamente.');
    }
    echo "El demo ya fue preparado. No se modificaron usuarios ni el avance del crédito.\nCredenciales: $reportPath\n";
    exit(0);
}
$accounts = ['gerencia' => 'Gerente', 'gestion' => 'Gestión', 'comercial' => 'Coordinación Comercial', 'caja' => 'Caja', 'promotor' => 'Promotor'];
foreach ($accounts as $key => $role) {
    if (User::where('email', "demo.$key@example.test")->exists()) {
        throw new RuntimeException('Ya existe una cuenta demo; revisar el registro anterior antes de continuar.');
    }
    Role::findByName($role, 'web');
}
if (Branch::withTrashed()->where('name', 'DEMO · Pruebas locales')->exists()) {
    throw new RuntimeException('La sucursal demo ya existe; no se duplicará ni reiniciará.');
}
if (! DB::table('migrations')->where('migration', '2026_10_14_020000_add_credit_disbursements')->exists()) {
    throw new RuntimeException('Falta la migración de desembolsos.');
}
if (! Rate::where('name', 'Cobre')->where('is_active', true)->where('calculation_method', 'weekly_per_thousand')->where('rate_factor', '80')->exists()) {
    throw new RuntimeException('Se requiere Cobre con factor semanal 80.');
}
$password = 'Demo!'.bin2hex(random_bytes(7)).'Aa9';
$curp = static function (string $base): string {
    $alphabet = preg_split('//u', '0123456789ABCDEFGHIJKLMNÑOPQRSTUVWXYZ', -1, PREG_SPLIT_NO_EMPTY);
    $sum = 0;
    for ($i = 0; $i < 17; $i++) {
        $sum += array_search($base[$i], $alphabet, true) * (18 - $i);
    }

    return $base.((10 - $sum % 10) % 10);
};
$report = DB::transaction(function () use ($accounts, $password, $curp, $reportPath) {
    $branch = Branch::create(['name' => 'DEMO · Pruebas locales', 'city' => 'Localidad ficticia DEMO', 'is_active' => true]);
    $users = [];
    $credentials = [];
    foreach ($accounts as $key => $role) {
        $user = new User;
        $user->forceFill(['name' => 'DEMO · '.$role, 'email' => "demo.$key@example.test", 'password' => $password, 'branch_id' => $branch->id, 'is_active' => true])->save();
        $user->assignRole($role);
        $users[$key] = $user;
        $credentials[] = ['role' => $role, 'email' => $user->email, 'password' => $password, 'id' => $user->id];
    }
    Promoter::create(['id' => $users['promotor']->id, 'branch_id' => $branch->id, 'is_active' => true]);
    $team = Team::create(['name' => 'DEMO · Las Semillas', 'branch_id' => $branch->id, 'promoter_id' => $users['promotor']->id, 'created_by' => $users['gerencia']->id, 'is_active' => true]);
    $clients = [];
    foreach ([['Alfa', 'PUDA900101MQRRML0', '1990-01-01', 'presidenta'], ['Beta', 'PUDB910202MQRRMT0', '1991-02-02', 'tesorera'], ['Gama', 'PUDG920303MQRRMM0', '1992-03-03', 'integrante']] as $i => [$name, $base, $birthday, $role]) {
        $identity = $curp($base);
        if (Client::withTrashed()->where('curp', $identity)->exists()) {
            throw new RuntimeException('La CURP sintética ya existe. No se modifica el cliente existente.');
        }
        $fields = [];
        foreach (app(ApplicationFields::class)->schema() as $field) {
            if (! $field['required']) {
                continue;
            }
            $fields[$field['name']] = $field['options'] ? (string) $field['options'][0]['value'] : match ($field['control']) {
                'date' => now('America/Cancun')->toDateString(), 'number' => '0', default => 'DEMO - dato ficticio'
            };
        }
        $fields = array_replace($fields, [
            'pNombre' => $name, 'sNombre' => 'DEMO', 'aPaterno' => 'Prueba', 'aMaterno' => 'Demo', 'genero' => 'Femenino', 'fNacimiento' => $birthday,
            'lNacimiento' => 'Quintana Roo', 'pais' => 'México', 'nacionalidad' => 'Mexicana', 'tidentificaion' => 'DEMO - sin validez oficial',
            'rfc' => substr($identity, 0, 10).'XXX', 'nIdentificacion' => 'DEMO-ID-00'.($i + 1), 'ecivil' => 'Soltero', 'regimen' => 'Ninguno', 'nescolar' => 'Secundaria',
            'oficio' => 'Comerciante DEMO', 'dudcalle' => 'Calle ficticia DEMO', 'dudne' => (string) (100 + $i), 'dudni' => 'Sin interior', 'dudcolonia' => 'Colonia DEMO',
            'dudelegacion' => 'Municipio DEMO', 'dudciudad' => 'Localidad DEMO', 'dudcp' => '00000', 'dudreferencia' => 'DOMICILIO FICTICIO. No realizar visitas reales.',
            'dudusotelefono' => 'Celular', 'dudtelefono' => '0000000000', 'dudrecidencia' => 'Familiar', 'dfchijos' => '0', 'dfcdeconomicos' => '1',
            'dseactividad' => 'Venta simulada', 'dseofinggresos' => 'Negocio', 'dseihogar' => '12000', 'dsenphabitan' => '2', 'dseccvivienda' => '1500',
            'rfNombre' => 'Referencia familiar DEMO', 'rfParentesco' => 'Hermana DEMO', 'rfTelefono' => '0000000000', 'rfDomicilio' => 'Domicilio ficticio DEMO', 'rfcp' => '00000', 'rfOcupacion' => 'Comercio DEMO', 'rfLTrabajo' => 'Negocio ficticio DEMO',
            'rpNombre' => 'Referencia personal DEMO', 'rpParentesco' => 'Amistad DEMO', 'rpTelefono' => '0000000000', 'rpDomicilio' => 'Domicilio ficticio DEMO', 'rpcp' => '00000', 'rpOcupacion' => 'Comercio DEMO', 'rpLTrabajo' => 'Negocio ficticio DEMO',
            'tfdirectivos' => '0', 'dNombre' => '0', 'Observaciones' => 'EXPEDIENTE SINTÉTICO PARA PRUEBAS LOCALES. Sin documentación real ni validación RENAPO. No realizar pagos, transferencias o contactos reales.',
        ]);
        $file = app(SaveApplicationAction::class)->execute($users['gerencia'], SaveApplicationData::fromArray(['id' => (string) Str::uuid(), 'version' => 0, 'team_id' => $team->id, 'curp' => $identity, 'confirm_existing' => false, 'submit' => true, 'fields' => $fields]));
        $membership = $team->memberships()->where('client_id', $file->client_id)->firstOrFail();
        app(AssignCommitteeAction::class)->execute($team, $membership->id, $role);
        $clients[] = ['name' => $name.' DEMO Prueba Demo', 'curp' => $identity, 'folio' => $file->client->folio, 'role' => $role, 'id' => $file->client_id, 'file_id' => $file->id];
    }
    $eligibility = app(CreditEligibility::class)->context($users['gerencia'], $team->id);
    if ($eligibility['issues'] || $eligibility['members']->contains(fn ($member) => ! $member['eligible'])) {
        throw new RuntimeException('El grupo demo no quedó elegible.');
    }
    foreach ($users as $user) {
        if (! Hash::check($password, $user->getAuthPassword())) {
            throw new RuntimeException('No se validó la contraseña del usuario demo.');
        }
    }
    $report = ['created_at' => now()->toISOString(), 'branch' => ['id' => $branch->id, 'name' => $branch->name, 'folio_code' => $branch->folio_code], 'team' => ['id' => $team->id, 'name' => $team->name], 'users' => $credentials, 'clients' => $clients, 'loan_created' => false];
    // Write only to ignored private storage, never to repository documentation.
    if (file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false || ! chmod($reportPath, 0600)) {
        throw new RuntimeException('No fue posible guardar las credenciales privadas.');
    }

    return $report;
});
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
echo "Credenciales guardadas: $reportPath\n";
