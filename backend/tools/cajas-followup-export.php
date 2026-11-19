<?php

declare(strict_types=1);

// Local restored central database only. Do not bootstrap Laravel or expose credentials.
require dirname(__DIR__).'/vendor/autoload.php';
$temporary = null;
try {
    if ($argc !== 3 || ! is_file($argv[1]) || file_exists($argv[2]) || is_link($argv[2]) || ! is_dir(dirname($argv[2]))) {
        throw new RuntimeException('Invalid paths.');
    }
    $config = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $host = $config['host'] ?? '127.0.0.1';
    $port = $config['port'] ?? 3306;
    $database = $config['database'] ?? '';
    if (! in_array($host, ['127.0.0.1', 'localhost', '::1'], true) || ! is_int($port) || $port < 1 || $port > 65535 || ! preg_match('/^[A-Za-z0-9_]+$/D', $database)) {
        throw new RuntimeException('Local source required.');
    }
    $user = getenv($config['user_env'] ?? 'LEGACY_DB_USER');
    $password = getenv($config['password_env'] ?? 'LEGACY_DB_PASSWORD');
    if (! is_string($user) || $user === '' || ! is_string($password)) {
        throw new RuntimeException('Configure credentials in the environment.');
    }
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_STRINGIFY_FETCHES => true]);
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->exec('START TRANSACTION READ ONLY');
    $selections = ['database_connections' => 'id, office_name, db_name', 'users' => 'id, name',
        'payment_observation_entries' => 'id, database_connection_id, credit_group_id, credit_id, team_id, demand_id, group_name, client_name, observation, user_id, author_name, is_cleared, created_at',
        'overdue_payment_notes' => 'id, database_connection_id, credit_group_id, observation, updated_by, created_at, updated_at'];
    $tables = [];
    foreach ($selections as $table => $fields) {
        $tables[$table] = $pdo->query("SELECT $fields FROM `$table` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    }
    $pdo->commit();
    $json = json_encode(['source_id' => 'cajas_central', 'extracted_at' => gmdate('c'), 'tables' => $tables], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (strlen($json) > 50 * 1024 * 1024) {
        throw new RuntimeException('Pilot size exceeded.');
    }
    $temporary = $argv[2].'.partial-'.bin2hex(random_bytes(8));
    $handle = fopen($temporary, 'xb');
    chmod($temporary, 0600);
    if (! $handle || fwrite($handle, $json) !== strlen($json)) {
        throw new RuntimeException('Incomplete output.');
    }
    fclose($handle);
    if (! link($temporary, $argv[2])) {
        throw new RuntimeException('Output already exists.');
    }
    unlink($temporary);
    $temporary = null;
    echo json_encode(['tables' => array_map('count', $tables), 'sha256' => hash_file('sha256', $argv[2]), 'operational_writes' => false], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable) {
    if (is_string($temporary) && is_file($temporary)) {
        unlink($temporary);
    }
    fwrite(STDERR, "Export rejected. Usage: php tools/cajas-followup-export.php LOCAL_CENTRAL_CONFIG.json NEW_PRIVATE_SNAPSHOT.json. No database was changed.\n");
    exit(1);
}
