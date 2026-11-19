<?php
// Alta inicial exclusiva del entorno de pruebas. Nunca sirve peticiones HTTP.
if (PHP_SAPI !== 'cli' || __DIR__ !== '/home/creceimp/jenkins-creceimpulso-pruebas') exit(1);
umask(0077);
$base = __DIR__;
$request = $base.'/admin-bootstrap.env';
if (!is_file($request)) exit;
$lock = fopen($base.'/admin-bootstrap.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit;
if (is_file($base.'/admin-bootstrap.done.json')) exit;
require $base.'/current/backend/vendor/autoload.php';
$input = Dotenv\Dotenv::parse(file_get_contents($request));
if (($input['ADMIN_CONFIRM'] ?? '') !== 'true') exit;
$status = ['ok' => false, 'time' => gmdate('c')];
try {
    $name = trim($input['ADMIN_NAME'] ?? '');
    $email = strtolower(trim($input['ADMIN_EMAIL'] ?? ''));
    $password = $input['ADMIN_PASSWORD'] ?? '';
    if ($name === '' || mb_strlen($name) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || strlen($password) < 14 || strlen($password) > 128 || !preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password) || str_contains($password, 'PENDIENTE_DE_CONFIGURAR')) throw new RuntimeException('Datos de alta no válidos');
    $app = require $base.'/current/backend/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if (!app()->environment('staging') || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'creceimp_pruebas' || config('app.url') !== 'https://pruebas.creceimpulso.com') throw new RuntimeException('Entorno no permitido');
    $user = Illuminate\Support\Facades\DB::transaction(function () use ($name, $email, $password) {
        if (App\Models\User::withTrashed()->count() !== 0) throw new RuntimeException('El entorno ya tiene usuarios; se requiere revisión manual');
        $role = Spatie\Permission\Models\Role::findOrCreate('Super Admin', 'web');
        $role->syncPermissions(Spatie\Permission\Models\Permission::where('guard_name', 'web')->get());
        $user = new App\Models\User();
        $user->name = $name;
        $user->email = $email;
        $user->password = $password;
        $user->is_active = true;
        $user->save();
        $user->assignRole($role);
        return $user;
    });
    $status['ok'] = true;
    $status['user_id'] = $user->id;
    $status['email'] = $email;
    $status['role'] = 'Super Admin';
    file_put_contents($base.'/admin-bootstrap.done.json', json_encode($status, JSON_THROW_ON_ERROR), LOCK_EX);
    // La contraseña en claro solo se usa para este alta y se retira del archivo.
    unlink($request);
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
} catch (Throwable $e) {
    // No imprimir contraseñas, consultas SQL ni trazas en el registro de cron.
    $status['error_type'] = get_class($e);
    $status['error_code'] = (string)$e->getCode();
}
file_put_contents($base.'/admin-bootstrap-status.json', json_encode($status, JSON_THROW_ON_ERROR), LOCK_EX);
