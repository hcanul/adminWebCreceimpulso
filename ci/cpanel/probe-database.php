<?php
// Diagnóstico temporal de CLI. No publica archivos ni modifica la base de datos.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
umask(0077);
$base = __DIR__;
$tools = ['proc_open' => function_exists('proc_open'), 'symlink' => function_exists('symlink'), 'mysqldump' => is_executable('/usr/bin/mysqldump'), 'mariadb_dump' => is_executable('/usr/bin/mariadb-dump')];
file_put_contents($base.'/tools.json', json_encode($tools));
$report = $base.'/database-check.json';
if (is_file($report)) exit;
$lock = fopen($base.'/database-check.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit;
$result = ['ok' => false, 'php' => PHP_VERSION, 'time' => gmdate('c')];
try {
    $archive = $base.'/validation.zip';
    $expected = trim(file_get_contents($base.'/validation.sha256'));
    if (!preg_match('/^[a-f0-9]{64}$/', $expected) || !hash_equals($expected, hash_file('sha256', $archive))) {
        throw new RuntimeException('package_checksum');
    }
    $zip = new ZipArchive();
    if ($zip->open($archive) !== true) throw new RuntimeException('package_open');
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        $opsys = $attr = 0;
        $zip->getExternalAttributesIndex($i, $opsys, $attr);
        if (str_contains($name, '\\') || str_starts_with($name, '/') || preg_match('~(^|/)\.\.?(/|$)~', $name) || str_contains($name, "\0") || (($attr >> 16) & 0170000) === 0120000) {
            throw new RuntimeException('package_path');
        }
        if (str_starts_with($name, 'backend/vendor/')) $names[] = $name;
    }
    $runtime = $base.'/validation-runtime';
    if (!is_dir($runtime) && !mkdir($runtime, 0700)) throw new RuntimeException('runtime_directory');
    if (!$zip->extractTo($runtime, $names)) throw new RuntimeException('runtime_extract');
    $zip->close();
    require $runtime.'/backend/vendor/autoload.php';
    $env = Dotenv\Dotenv::parse(file_get_contents($base.'/shared.env'));
    if (($env['DB_DATABASE'] ?? '') !== 'creceimp_pruebas' || ($env['DB_USERNAME'] ?? '') !== 'creceimp_pruebas' || ($env['DB_HOST'] ?? '') !== 'localhost') {
        throw new RuntimeException('database_scope');
    }
    $pdo = new PDO('mysql:host=localhost;dbname=creceimp_pruebas;charset=utf8mb4', 'creceimp_pruebas', $env['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $result['database'] = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $result['tables'] = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
    $result['ok'] = true;
    $result['vendor_platform_check'] = 'passed';
} catch (Throwable $e) {
    // No guardar mensajes de conexión, contraseñas ni trazas en el informe.
    $result['error_type'] = get_class($e);
    $result['error_code'] = (string) $e->getCode();
}
file_put_contents($report.'.tmp', json_encode($result, JSON_PRETTY_PRINT), LOCK_EX);
rename($report.'.tmp', $report);
