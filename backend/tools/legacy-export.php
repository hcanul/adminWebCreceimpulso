<?php

declare(strict_types=1);

// Local restored legacy databases only. No Laravel bootstrap, target connection, DDL or DML.
require dirname(__DIR__).'/vendor/autoload.php';
use App\Domains\LegacyMigration\Actions\BuildConsolidationPlanAction as Plan;
use App\Domains\LegacyMigration\DTOs\SourceSnapshot;

$temporary = null;
$handle = null;
$pdo = null;
try {
    if ($argc !== 3 || ! is_file($argv[1]) || file_exists($argv[2]) || is_link($argv[2]) || ! is_dir(dirname($argv[2]))) {
        throw new RuntimeException('Invalid paths.');
    }
    $config = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($config['sources'] ?? null) || ! array_is_list($config['sources']) || ! count($config['sources'])) {
        throw new RuntimeException('Missing source list.');
    }
    $temporary = $argv[2].'.partial-'.bin2hex(random_bytes(8));
    $handle = fopen($temporary, 'xb');
    chmod($temporary, 0600);
    $bytes = 0;
    $write = function (string $text) use ($handle, &$bytes) {
        $bytes += strlen($text);
        if ($bytes > 50 * 1024 * 1024 || fwrite($handle, $text) !== strlen($text)) {
            throw new RuntimeException('Pilot output size or disk limit.');
        }
    };
    $encode = fn ($v) => json_encode($v, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $write('{"sources":[');
    $seen = [];
    foreach ($config['sources'] as $i => $source) {
        $id = $source['source_id'] ?? '';
        $branches = $source['branches'] ?? [];
        new SourceSnapshot($id, $branches, []);
        if (isset($seen[$id])) {
            throw new RuntimeException('Duplicate source.');
        } $seen[$id] = true;
        $host = $source['host'] ?? '127.0.0.1';
        $port = $source['port'] ?? 3306;
        $database = $source['database'] ?? '';
        if (! in_array($host, ['127.0.0.1', 'localhost', '::1'], true) || ! is_int($port) || $port < 1 || $port > 65535 || ! preg_match('/^[a-zA-Z0-9_]+$/D', $database)) {
            throw new RuntimeException('Invalid local database.');
        }
        $user = getenv($source['user_env'] ?? 'LEGACY_DB_USER');
        $password = getenv($source['password_env'] ?? 'LEGACY_DB_PASSWORD');
        if ($user === false || $user === '' || $password === false) {
            throw new RuntimeException('Set local credential environment variables.');
        }
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_STRINGIFY_FETCHES => true, PDO::ATTR_EMULATE_PREPARES => false, PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false]);
        $stmt = $pdo->prepare('SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?');
        $stmt->execute([$database, 'BASE TABLE']);
        $engines = [];
        foreach ($stmt as $row) {
            $engines[$row['TABLE_NAME']] = $row['ENGINE'];
        }$stmt->closeCursor();
        $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->exec('SET TRANSACTION READ ONLY');
        $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
        if ($i) {
            $write(',');
        }
        $write('{"source_id":'.$encode($id).',"branches":'.$encode((object) $branches).',"tables":{');
        $schema = [];
        $ddl = [];
        $first = true;
        foreach (Plan::TABLES as $table) {
            if (! isset($engines[$table])) {
                continue;
            }
            if (strtoupper($engines[$table]) !== 'INNODB') {
                throw new RuntimeException('Nontransactional source requires a consistent restored backup.');
            }
            $columns = $pdo->query('SHOW COLUMNS FROM `'.$table.'`');
            $schema[$table] = $columns->fetchAll(PDO::FETCH_ASSOC);
            $columns->closeCursor();
            $create = $pdo->query('SHOW CREATE TABLE `'.$table.'`');
            $definition = $create->fetch(PDO::FETCH_NUM);
            $ddl[$table] = $definition[1] ?? null;
            $create->closeCursor();
            if (! $first) {
                $write(',');
            }$first = false;
            $write($encode($table).':[');
            $firstRow = true;
            $rows = $pdo->query('SELECT * FROM `'.$table.'` ORDER BY `id`');
            while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                if (! $firstRow) {
                    $write(',');
                }$firstRow = false;
                $write($encode($row));
            }
            $rows->closeCursor();
            $write(']');
        }
        $write('},"extraction":'.$encode(['captured_at_utc' => gmdate('c'), 'database' => $database, 'schema' => $schema, 'ddl' => $ddl, 'scope' => Plan::TABLES, 'inventory_engines' => $engines]).'}');
        $pdo->exec('ROLLBACK');
        $pdo = null;
    }
    $write(']}');
    fclose($handle);
    $handle = null;
    if (file_exists($argv[2]) || is_link($argv[2]) || ! rename($temporary, $argv[2])) {
        throw new RuntimeException('Destination unavailable.');
    }$temporary = null;
    echo json_encode(['source_count' => count($seen), 'bytes' => $bytes, 'sha256' => hash_file('sha256', $argv[2]), 'operational_writes' => 0], JSON_PRETTY_PRINT).PHP_EOL;
} catch (Throwable) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (is_resource($handle)) {
        fclose($handle);
    }if ($temporary && is_file($temporary)) {
        unlink($temporary);
    }
    fwrite(STDERR, "Export rejected. Use: php tools/legacy-export.php private-sources.json NEW_SNAPSHOT.json. Requires local restored InnoDB databases, credential environment variables and a new output path. Pilot limit: 50 MiB. No target import was performed.\n");
    exit(1);
}
