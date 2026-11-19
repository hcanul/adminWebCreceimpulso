<?php
if (PHP_SAPI !== 'cli' || __DIR__ !== '/home/creceimp/jenkins-creceimpulso-pruebas') exit(1);
umask(0077);
$base = __DIR__;
$public = '/home/creceimp/pruebas.creceimpulso.com';
$operation = $argv[1] ?? '';
function requestData(): array {
    $data = json_decode(file_get_contents(__DIR__.'/processing.json'), true, 16, JSON_THROW_ON_ERROR);
    if (!preg_match('/^ci-[0-9]+-[a-f0-9]{12}$/', $data['release'] ?? '') || !preg_match('/^[a-f0-9]{40}$/', $data['revision'] ?? '') || !str_ends_with($data['release'], substr($data['revision'], 0, 12)) || !preg_match('/^[a-f0-9]{64}$/', $data['sha256'] ?? '')) throw new RuntimeException('Manifest inválido');
    return $data;
}
if ($operation === 'ready') {
    $ready = is_executable('/usr/bin/mysqldump') && is_executable('/usr/bin/flock') && is_executable('/usr/bin/ln') && class_exists('ZipArchive');
    file_put_contents($base.'/runner-ready.json', json_encode(['ready' => $ready, 'php' => PHP_VERSION, 'time' => gmdate('c')]));
    exit($ready ? 0 : 1);
}
if ($operation === 'report') {
    try { $data = requestData(); $id = $data['release']; } catch (Throwable) { $id = 'invalid-request'; }
    $status = ['release' => $id, 'state' => $argv[2], 'phase' => $argv[3], 'time' => gmdate('c')];
    file_put_contents($base.'/status.json.tmp', json_encode($status, JSON_THROW_ON_ERROR), LOCK_EX);
    rename($base.'/status.json.tmp', $base.'/status.json');
    exit;
}
$data = requestData();
$id = $data['release'];
$release = $base.'/releases/'.$id;
if ($operation === 'prepare') {
    if (!hash_equals($data['sha256'], hash_file('sha256', $base.'/incoming.zip'))) throw new RuntimeException('SHA-256 incorrecto');
    if (file_exists($release)) throw new RuntimeException('La entrega ya existe');
    $zip = new ZipArchive();
    if ($zip->open($base.'/incoming.zip') !== true) throw new RuntimeException('ZIP inválido');
    if (trim($zip->getFromName('REVISION') ?: '') !== $data['revision']) throw new RuntimeException('Revisión incorrecta');
    $api = array_fill_keys(['admin-web', 'client-app', 'staff-app'], false);
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        $os = $attr = 0; $zip->getExternalAttributesIndex($i, $os, $attr);
        if (str_starts_with($name, '/') || str_contains($name, '\\') || str_contains($name, "\0") || preg_match('~(^|/)\.\.?(/|$)|(^|/)\.env(?:\.|$)~', $name) || (($attr >> 16) & 0170000) === 0120000 || !(str_starts_with($name, 'backend/') || str_starts_with($name, 'web/') || $name === 'REVISION')) throw new RuntimeException('Ruta de ZIP no permitida');
        if (str_starts_with($name, 'web/') && preg_match('/\.(php[0-9]?|phtml|phar)$/i', $name)) throw new RuntimeException('PHP en archivos estáticos');
        if (preg_match('~^web/(admin-web|client-app|staff-app)/assets/.+\.js$~', $name, $match) && str_contains($zip->getFromIndex($i), 'https://pruebas.creceimpulso.com/api')) $api[$match[1]] = true;
    }
    if (in_array(false, $api, true)) throw new RuntimeException('API de pruebas ausente en una aplicación');
    if (!mkdir($release, 0700, true) || !$zip->extractTo($release)) throw new RuntimeException('No se pudo extraer la entrega');
    $zip->close();
    foreach (['admin-web', 'client-app', 'staff-app'] as $app) if (!is_file($release.'/web/'.$app.'/index.html')) throw new RuntimeException('Falta una aplicación');
    require $release.'/backend/vendor/autoload.php';
    $env = Dotenv\Dotenv::parse(file_get_contents($base.'/shared.env'));
    if (($env['DB_DATABASE'] ?? '') !== 'creceimp_pruebas' || ($env['DB_USERNAME'] ?? '') !== 'creceimp_pruebas' || ($env['DB_HOST'] ?? '') !== 'localhost' || ($env['APP_URL'] ?? '') !== 'https://pruebas.creceimpulso.com' || ($env['APP_ENV'] ?? '') !== 'staging' || ($env['APP_DEBUG'] ?? '') !== 'false') throw new RuntimeException('Configuración fuera del entorno de pruebas');
    $pdo = new PDO('mysql:host=localhost;dbname=creceimp_pruebas;charset=utf8mb4', 'creceimp_pruebas', $env['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    foreach ($pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) as $table) if ($table[1] !== 'BASE TABLE') throw new RuntimeException('Revisar respaldo de vistas antes de desplegar');
    $quote = fn(string $s): string => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $s).'"';
    file_put_contents($base.'/mysql-client.cnf', "[client]\nhost=localhost\nuser=creceimp_pruebas\npassword=".$quote($env['DB_PASSWORD'])."\n");
    chmod($base.'/mysql-client.cnf', 0600);
    file_put_contents($base.'/release-id', $id."\n");
    exit;
}
if ($operation === 'compress-backup') {
    $input = $base.'/backups/'.$id.'.sql';
    $source = fopen($input, 'rb'); $target = gzopen($input.'.gz', 'wb6');
    if (!$source || !$target) throw new RuntimeException('No se pudo abrir el respaldo');
    while (!feof($source)) { $chunk = fread($source, 1048576); if ($chunk === false || gzwrite($target, $chunk) === false) throw new RuntimeException('Falló la compresión'); }
    fclose($source); gzclose($target);
    if (!is_file($input.'.gz') || filesize($input.'.gz') === 0) throw new RuntimeException('Respaldo vacío');
    unlink($input);
    exit;
}
if ($operation === 'public-config') {
    $htaccess = file_exists($public.'/.htaccess') ? file_get_contents($public.'/.htaccess') : '';
    if (!str_contains($htaccess, '# BEGIN Jenkins Creceimpulso Pruebas')) {
        copy($public.'/.htaccess', $base.'/backups/htaccess-before-jenkins');
        $htaccess .= <<<'RULES'

# BEGIN Jenkins Creceimpulso Pruebas
Options -Indexes
DirectoryIndex index.php
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^\.well-known/ - [L]
RewriteCond %{DOCUMENT_ROOT}/.jenkins-maintenance -f
RewriteRule ^ - [R=503,L]
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteCond %{HTTP:x-xsrf-token} .
RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-Token}]
RewriteRule ^$ /admin/ [R=302,L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]
</IfModule>
<FilesMatch "^(\.env|composer\.(json|lock)|artisan|.*\.sql(\.gz)?)$">
Require all denied
</FilesMatch>
# END Jenkins Creceimpulso Pruebas
RULES;
        file_put_contents($public.'/.htaccess.jenkins.tmp', $htaccess); chmod($public.'/.htaccess.jenkins.tmp', 0644);
        rename($public.'/.htaccess.jenkins.tmp', $public.'/.htaccess');
    }
    if (!is_file($public.'/index.php')) {
        $controller = <<<'FRONT'
<?php
if (is_file(__DIR__.'/.jenkins-maintenance')) { http_response_code(503); exit('Servicio en mantenimiento'); }
require '/home/creceimp/jenkins-creceimpulso-pruebas/current/backend/public/index.php';
FRONT;
        file_put_contents($public.'/index.php', $controller); chmod($public.'/index.php', 0644);
    } elseif (!str_contains(file_get_contents($public.'/index.php'), '/jenkins-creceimpulso-pruebas/current/backend/public/index.php')) throw new RuntimeException('Hay un controlador público ajeno al despliegue');
    exit;
}
if ($operation === 'health') {
    require $base.'/current/backend/vendor/autoload.php';
    $app = require $base.'/current/backend/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $request = Illuminate\Http\Request::create('https://pruebas.creceimpulso.com/up');
    $response = $kernel->handle($request);
    if ($response->getStatusCode() !== 200) throw new RuntimeException('Laravel no pasó la comprobación local');
    Illuminate\Support\Facades\DB::select('SELECT 1');
    $kernel->terminate($request, $response);
    exit;
}
throw new RuntimeException('Operación no permitida');
