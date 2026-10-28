<?php

use Illuminate\Contracts\Console\Kernel;

// Only a randomly named disposable database is created, migrated and removed.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$c = config('database.connections.mysql');
if (! in_array($c['host'], ['localhost', '127.0.0.1', '::1'], true) || ! empty($c['url'])) {
    fwrite(STDERR, "Solo se admite MySQL local sin DB_URL.\n");
    exit(1);
}
$name = 'crece_verify_'.bin2hex(random_bytes(8));
$path = dirname(__DIR__).'/phpunit.verify-'.$name.'.xml';
$created = false;
$exit = 1;
try {
    $dsn = ! empty($c['unix_socket']) ? 'mysql:unix_socket='.$c['unix_socket'] : 'mysql:host='.$c['host'].';port='.$c['port'];
    $pdo = new PDO($dsn.';charset=utf8mb4', $c['username'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
    $pdo->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    $xml = file_get_contents(dirname(__DIR__).'/phpunit.intake.xml');
    $xml = str_replace(['name="DB_CONNECTION" value="sqlite"', 'name="DB_DATABASE" value=":memory:"'], ['name="DB_CONNECTION" value="mysql"', 'name="DB_DATABASE" value="'.$name.'"'], $xml);
    if (file_put_contents($path, $xml) === false) {
        throw new RuntimeException('Temporary configuration failed.');
    }
    $env = array_merge(getenv(), ['DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $name, 'DB_URL' => '', 'DB_HOST' => $c['host'], 'DB_PORT' => (string) $c['port'], 'DB_USERNAME' => $c['username'], 'DB_PASSWORD' => $c['password'], 'DB_SOCKET' => $c['unix_socket'] ?? '']);
    echo "Pruebas aisladas en MySQL; la base de trabajo no se migra.\n";
    $process = proc_open([PHP_BINARY, 'vendor/bin/phpunit', '-c', basename($path)], [STDIN, STDOUT, STDERR], $pipes, dirname(__DIR__), $env);
    if (! is_resource($process)) {
        throw new RuntimeException('Could not start PHPUnit.');
    }
    $exit = proc_close($process);
} catch (Throwable $e) {
    fwrite(STDERR, 'No se pudo completar la verificación aislada ('.get_class($e).').'.PHP_EOL);
} finally {
    if (is_file($path)) {
        unlink($path);
    }
    if ($created) {
        try {
            $pdo->exec('DROP DATABASE `'.$name.'`');
            echo "Base temporal eliminada.\n";
        } catch (Throwable) {
            fwrite(STDERR, 'No se pudo eliminar la base temporal '.$name.'.'.PHP_EOL);
            $exit = 1;
        }
    }
}
exit($exit);
