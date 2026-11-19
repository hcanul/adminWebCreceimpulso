<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use App\Domains\LegacyMigration\Actions\ApplyBranchClientsTrialAction;
use App\Domains\LegacyMigration\Actions\BuildBranchClientPlanAction;
use App\Domains\LegacyMigration\Services\BranchTrialSchema;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

function readJson(string $path): array
{
    if (! is_file($path) || is_link($path) || filesize($path) > 50 * 1024 * 1024) {
        throw new RuntimeException('Invalid private JSON input.');
    }

    return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
}
function newDirectory(string $path): void
{
    if (file_exists($path) || is_link($path) || ! is_dir(dirname($path)) || ! mkdir($path, 0700)) {
        throw new RuntimeException('Output directory must be new.');
    }
}
function saveJson(string $path, array $data): void
{
    $text = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (strlen($text) > 50 * 1024 * 1024) {
        throw new RuntimeException('Pilot output limit exceeded.');
    }
    $h = fopen($path, 'xb');
    if (! $h) {
        throw new RuntimeException('Output already exists.');
    }
    chmod($path, 0600);
    if (fwrite($h, $text) !== strlen($text)) {
        throw new RuntimeException('Incomplete output.');
    } fclose($h);
}
function localConnection(array $config, bool $databaseRequired = true): array
{
    $host = $config['host'] ?? '127.0.0.1';
    $port = $config['port'] ?? 3306;
    $database = $config['database'] ?? '';
    if (! in_array($host, ['127.0.0.1', 'localhost', '::1'], true) || ! is_int($port) || $port < 1 || $port > 65535 || ($databaseRequired && ! preg_match('/^[A-Za-z0-9_]+$/D', $database))) {
        throw new RuntimeException('Only local restored MySQL is permitted.');
    }
    $userKey = $config['user_env'] ?? 'LEGACY_DB_USER';
    $passwordKey = $config['password_env'] ?? 'LEGACY_DB_PASSWORD';
    foreach ([$userKey, $passwordKey] as $key) {
        if (! is_string($key) || ! preg_match('/^[A-Z][A-Z0-9_]*$/D', $key)) {
            throw new RuntimeException('Invalid credential variable.');
        }
    }
    $user = getenv($userKey);
    $password = getenv($passwordKey);
    if (! is_string($user) || $user === '' || ! is_string($password)) {
        throw new RuntimeException('Set local credential environment variables.');
    }

    return ['host' => $host, 'port' => $port, 'database' => $database, 'username' => $user, 'password' => $password, 'user_env' => $userKey, 'password_env' => $passwordKey];
}
function pdo(array $c, bool $selectDatabase): PDO
{
    return new PDO('mysql:host='.$c['host'].';port='.$c['port'].($selectDatabase ? ';dbname='.$c['database'] : '').';charset=utf8mb4', $c['username'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_STRINGIFY_FETCHES => true]);
}
function bootLab(array $c): void
{
    $default = require dirname(__DIR__).'/bootstrap/app.php';
    if (is_file(dirname(__DIR__).'/bootstrap/cache/config.php')) {
        throw new RuntimeException('Rehearsal needs uncached configuration; no cache files were changed.');
    }
    $app = $default;
    $app->make(Kernel::class)->bootstrap();
    config(['database.default' => 'mysql', 'database.connections.mysql' => ['driver' => 'mysql', 'host' => $c['host'], 'port' => $c['port'], 'database' => $c['database'], 'username' => $c['username'], 'password' => $c['password'], 'unix_socket' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true], 'cache.default' => 'array', 'session.driver' => 'array', 'client_push.enabled' => false, 'mail.default' => 'array']);
    DB::purge('mysql');
    if (DB::selectOne('SELECT DATABASE() AS name')->name !== $c['database']) {
        throw new RuntimeException('Lab connection mismatch.');
    }
}

$phase = 'arguments';
try {
    umask(0077);
    $mode = $argv[1] ?? '';
    if ($mode === '--help') {
        echo "Ensayo local por sucursal (clientes, no cartera financiera).\nplan CONFIG.json NUEVO_PLAN\nplan-snapshot SNAPSHOT.json NUEVO_PLAN\ninit-lab CONFIG.json NUEVO_LAB\napply PLAN_DIR LAB_DIR\n";
        exit(0);
    }
    if ($mode === 'plan' && $argc === 4) {
        $config = readJson($argv[2]);
        $c = localConnection($config['source'] ?? []);
        if (($config['source_timezone_confirmed'] ?? false) !== true) {
            throw new RuntimeException('Confirm source timezone in the private configuration.');
        }
        new DateTimeZone($config['source_timezone'] ?? 'INVALID');
        $db = pdo($c, true);
        $phase = 'read_restored_source';
        foreach (['demands', 'citys'] as $table) {
            $q = $db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
            $q->execute([$c['database'], $table]);
            if (strtoupper((string) $q->fetchColumn()) !== 'INNODB') {
                throw new RuntimeException('Restored source must use transactional tables.');
            }
        }
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->exec('START TRANSACTION READ ONLY');
        $columns = $db->query('SHOW COLUMNS FROM demands')->fetchAll(PDO::FETCH_COLUMN);
        foreach (['id', 'curp', 'city_id', 'pNombre', 'sNombre', 'aPaterno', 'aMaterno', 'dudtelefono', 'dudcalle', 'created_at', 'updated_at', 'deleted_at'] as $column) {
            if (! in_array($column, $columns, true)) {
                throw new RuntimeException('Legacy client schema differs; adapt mapping before proceeding.');
            }
        }
        if ((int) $db->query('SELECT COUNT(*) FROM demands')->fetchColumn() > 100000) {
            throw new RuntimeException('Pilot row limit exceeded.');
        }
        $rows = $db->query('SELECT * FROM demands ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $cities = $db->query('SELECT * FROM citys ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $db->rollBack();
        $branch = $config['branch'] ?? [];
        if (! in_array((string) ($branch['legacy_city_id'] ?? ''), array_map(fn ($r) => (string) $r['id'], $cities), true)) {
            throw new RuntimeException('Configured legacy city is absent.');
        }
        $snapshot = ['source_id' => $config['source_id'] ?? '', 'source_database' => $c['database'], 'source_timezone' => $config['source_timezone'] ?? '', 'source_timezone_confirmed' => true, 'backup_label' => $config['backup_label'] ?? '', 'branch' => $branch, 'citys' => $cities, 'demands' => $rows];
        if (strlen(json_encode($snapshot, JSON_THROW_ON_ERROR)) > 50 * 1024 * 1024) {
            throw new RuntimeException('Pilot snapshot limit exceeded.');
        }
        $plan = (new BuildBranchClientPlanAction)->execute($snapshot);
        newDirectory($argv[3]);
        saveJson($argv[3].'/snapshot.json', $snapshot);
        saveJson($argv[3].'/plan.json', $plan);
        file_put_contents($argv[3].'/SHA256.txt', hash_file('sha256', $argv[3].'/snapshot.json')."  snapshot.json\n".hash_file('sha256', $argv[3].'/plan.json')."  plan.json\n");
        echo json_encode(['source_rows' => $plan['source_rows'], 'accepted' => count($plan['accepted']), 'quarantined' => count($plan['quarantine']), 'ready_for_production' => false], JSON_PRETTY_PRINT).PHP_EOL;
    } elseif ($mode === 'plan-snapshot' && $argc === 4) {
        $snapshot = readJson($argv[2]);
        $plan = (new BuildBranchClientPlanAction)->execute($snapshot);
        newDirectory($argv[3]);
        saveJson($argv[3].'/snapshot.json', $snapshot);
        saveJson($argv[3].'/plan.json', $plan);
        echo json_encode(['source_rows' => $plan['source_rows'], 'accepted' => count($plan['accepted']), 'quarantined' => count($plan['quarantine']), 'ready_for_production' => false], JSON_PRETTY_PRINT).PHP_EOL;
    } elseif ($mode === 'init-lab' && $argc === 4) {
        $config = readJson($argv[2]);
        $c = localConnection($config['lab_connection'] ?? [], false);
        newDirectory($argv[3]);
        $phase = 'create_new_lab';
        $admin = pdo($c, false);
        $c['database'] = 'crece_branch_trial_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
        $admin->exec('CREATE DATABASE `'.$c['database'].'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $marker = Uuid::uuid4()->toString();
        $metadata = array_intersect_key($c, array_flip(['host', 'port', 'database', 'user_env', 'password_env']));
        $metadata['marker'] = $marker;
        $metadata['ready_for_production'] = false;
        saveJson($argv[3].'/lab.json', $metadata);
        $phase = 'migrate_lab';
        bootLab($c);
        $status = Artisan::call('migrate', ['--force' => true]);
        file_put_contents($argv[3].'/schema-migrations.log', Artisan::output());
        if ($status !== 0) {
            throw new RuntimeException('Lab schema migration failed.');
        }
        (new BranchTrialSchema)->create($marker);
        echo 'Laboratorio nuevo creado: '.$c['database'].PHP_EOL;
    } elseif ($mode === 'apply' && $argc === 4) {
        $metadata = readJson($argv[3].'/lab.json');
        $c = localConnection($metadata);
        if (! preg_match('/^crece_branch_trial_[0-9]{8}_[0-9]{6}_[a-f0-9]{6}$/D', $c['database']) || ! Uuid::isValid($metadata['marker'] ?? '') || ($metadata['ready_for_production'] ?? null) !== false) {
            throw new RuntimeException('Only marked rehearsal databases are accepted.');
        }
        $phase = 'verify_plan';
        $snapshot = readJson($argv[2].'/snapshot.json');
        $stored = readJson($argv[2].'/plan.json');
        $plan = (new BuildBranchClientPlanAction)->execute($snapshot);
        if ($stored !== $plan) {
            throw new RuntimeException('Plan differs from the snapshot or transformer version.');
        }
        $phase = 'apply_to_lab';
        if ($c['database'] === ($snapshot['source_database'] ?? null)) {
            throw new RuntimeException('Source and trial destination must be different.');
        }
        bootLab($c);
        $report = (new ApplyBranchClientsTrialAction)->execute($plan, $metadata['marker']);
        saveJson($argv[3].'/result-'.gmdate('Ymd_His').'-'.bin2hex(random_bytes(3)).'.json', $report);
        unset($report['issues']);
        echo json_encode($report, JSON_PRETTY_PRINT).PHP_EOL;
    } else {
        throw new RuntimeException('Usage: plan CONFIG NEW_PLAN_DIR | init-lab CONFIG NEW_LAB_DIR | apply PLAN_DIR LAB_DIR');
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Ensayo detenido en '.$phase.' ('.get_class($e).'). No se admite producción como destino. Revise configuración, archivos y permisos; no se imprimen credenciales ni datos personales.'.PHP_EOL);
    fwrite(STDERR, "Uso: php tools/branch-client-rehearsal.php plan CONFIG.json NUEVO_PLAN | init-lab CONFIG.json NUEVO_LAB | apply PLAN_DIR LAB_DIR\n");
    exit(1);
}
