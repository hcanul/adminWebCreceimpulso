<?php

declare(strict_types=1);

use App\Domains\Identity\Services\FolioSequence;
use App\Domains\LegacyMigration\Actions\BuildConsolidationPlanAction;
use App\Domains\LegacyMigration\Actions\PlanTrialClientsAction;
use App\Domains\LegacyMigration\Actions\RehearseRawImportAction;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';

// This pilot creates a NEW MySQL database. It cannot select an existing destination.
// Credentials are read in memory from Laravel; no config/cache/.env files are changed.
$phase = 'validate';
try {
    umask(0077);
    if ($argc !== 3 || ! is_dir($argv[1]) || file_exists($argv[2]) || ! is_dir(dirname($argv[2]))) {
        throw new RuntimeException('Usage: php tools/legacy-rehearse.php STAGING_ROOT NEW_REPORT_DIRECTORY');
    }
    mkdir($argv[2], 0700);
    $reportDirectory = realpath($argv[2]);
    $registry = json_decode(file_get_contents(dirname(__DIR__, 2).'/docs/migration/branches.registry.json'), true, 512, JSON_THROW_ON_ERROR);
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $config = config('database.connections.mysql');
    if (! in_array($config['host'], ['127.0.0.1', 'localhost', '::1'], true) || ! preg_match('/^[a-zA-Z0-9_]+$/D', $config['database']) || ! empty($config['url'])) {
        throw new RuntimeException('Only explicit local MySQL connections are supported.');
    }
    $connect = static fn () => new PDO('mysql:host='.$config['host'].';port='.$config['port'].';charset=utf8mb4', $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_STRINGIFY_FETCHES => true, PDO::ATTR_EMULATE_PREPARES => false]);
    $admin = $connect();
    $operational = $config['database'];
    $operationalCounts = static function () use ($admin, $operational): array {
        $query = $admin->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME');
        $query->execute([$operational, 'BASE TABLE']);
        $counts = [];
        foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $table) {
            if (! preg_match('/^[a-zA-Z0-9_]+$/D', $table)) {
                throw new RuntimeException('Unexpected operational table identifier.');
            }
            $counts[$table] = (int) $admin->query('SELECT COUNT(*) FROM `'.$operational.'`.`'.$table.'`')->fetchColumn();
        }

        return $counts;
    };
    $countsBefore = $operationalCounts();
    $branches = $sourceMaps = $primary = $liveVerification = [];
    $phase = 'verify_live_sources_read_only';
    // Verify the archived extracts still match local restored databases before any trial write.
    $reader = $connect();
    $reader->exec('SET SESSION TRANSACTION READ ONLY');
    $reader->beginTransaction();
    foreach ($registry['branches'] as $branch) {
        $sid = $branch['source_id'];
        $database = $branch['source_database'];
        if (! preg_match('/^[a-z0-9_]+$/D', $database) || $database === $operational) {
            throw new RuntimeException('Invalid legacy source.');
        }
        $manifest = json_decode(file_get_contents($argv[1].'/'.$sid.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $sourceMaps[$sid] = $manifest['sources'][0]['branches'];
        $primary[$sid] = $branch['legacy_city_id'];
        $query = $reader->prepare('SELECT * FROM `'.$operational.'`.branches WHERE id = ?');
        $query->execute([$branch['target_branch_uuid']]);
        $branches[] = $query->fetch(PDO::FETCH_ASSOC) ?: throw new RuntimeException('Missing registered target branch.');
        foreach ($manifest['files'] as $file) {
            $table = basename($file['path'], '.ndjson');
            if (! in_array($table, BuildConsolidationPlanAction::TABLES, true) || $file['path'] !== $sid.'/'.$table.'.ndjson') {
                throw new RuntimeException('Invalid staging path.');
            }
            $path = $argv[1].'/'.$sid.'/'.$file['path'];
            if (! hash_equals($file['sha256'], hash_file('sha256', $path))) {
                throw new RuntimeException('Staging file hash mismatch.');
            }
            $rows = $reader->query('SELECT * FROM `'.$database.'`.`'.$table.'` ORDER BY id');
            $handle = fopen($path, 'rb');
            $count = 0;
            try {
                while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                    $line = fgets($handle);
                    $archived = $line === false ? null : json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                    if (($archived['source_id'] ?? null) !== $sid || ($archived['raw'] ?? null) !== $row) {
                        throw new RuntimeException('Live source differs from snapshot; create a new extraction first.');
                    }
                    $count++;
                }
                if (fgets($handle) !== false || $count !== $file['rows']) {
                    throw new RuntimeException('Live source count mismatch.');
                }
            } finally {
                fclose($handle);
                $rows->closeCursor();
            }
            $liveVerification[$sid][$table] = $count;
        }
        echo 'Origen verificado: '.$sid.PHP_EOL;
    }
    $reader->rollBack();
    $phase = 'create_isolated_database';
    $trial = 'crece_migration_trial_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
    // CREATE without IF NOT EXISTS ensures an existing schema can never be reused.
    $admin->exec('CREATE DATABASE `'.$trial.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    file_put_contents($reportDirectory.'/database.txt', $trial."\n");
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => $trial, 'database.connections.mysql.url' => null]);
    DB::purge('mysql');
    if (DB::connection()->getDatabaseName() !== $trial || DB::selectOne('SELECT DATABASE() AS name')->name !== $trial) {
        throw new RuntimeException('Trial connection isolation failed.');
    }
    $phase = 'migrate_new_schema';
    $code = Artisan::call('migrate', ['--force' => true]);
    file_put_contents($reportDirectory.'/schema-migrations.log', Artisan::output());
    if ($code !== 0) {
        throw new RuntimeException('New schema migration failed.');
    }
    DB::table('branches')->insert($branches);
    $pdo = DB::connection()->getPdo();
    $raw = new RehearseRawImportAction;
    $raw->createSchema($pdo);
    $runs = [];
    // Second pass proves idempotence against exactly the same source files.
    for ($pass = 1; $pass <= 2; $pass++) {
        $phase = 'raw_import_pass_'.$pass;
        foreach ($registry['branches'] as $branch) {
            $sid = $branch['source_id'];
            $runs[$pass][] = $raw->execute($pdo, $argv[1].'/'.$sid);
            echo 'Carga cruda '.$pass.': '.$sid.PHP_EOL;
        }
    }
    $phase = 'project_unambiguous_clients';
    $records = [];
    foreach (DB::table('legacy_trial_rows')->where('table_name', 'demands')->orderBy('source_id')->orderBy('legacy_id')->cursor() as $record) {
        $records[] = ['source_id' => $record->source_id, 'raw' => json_decode($record->payload, true, 512, JSON_THROW_ON_ERROR)];
    }
    $plan = (new PlanTrialClientsAction)->execute($records, $sourceMaps, $primary);
    $pdo->exec('CREATE TABLE legacy_trial_client_profiles (client_id VARCHAR(36) PRIMARY KEY, source_id VARCHAR(64) NOT NULL, legacy_id VARCHAR(32) NOT NULL, fields LONGTEXT NOT NULL, FOREIGN KEY(client_id) REFERENCES clients(id))');
    $sequence = app(FolioSequence::class);
    $projected = [];
    DB::transaction(function () use ($plan, $sequence, &$projected): void {
        foreach ($plan['accepted'] as $item) {
            $client = $item['client'];
            $client['folio'] = $sequence->issue($client['branch_id'], 'client');
            DB::table('clients')->insert($client);
            DB::table('legacy_trial_client_profiles')->insert(['client_id' => $client['id'], 'source_id' => $item['source_id'], 'legacy_id' => $item['legacy_id'], 'fields' => json_encode($item['profile'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            $projected[] = $client;
        }
    });
    $phase = 'verify_client_projection';
    foreach ($projected as $expected) {
        $actual = (array) DB::table('clients')->where('id', $expected['id'])->first();
        foreach ($expected as $key => $value) {
            if ((string) $actual[$key] !== (string) $value || ($actual[$key] === null) !== ($value === null)) {
                throw new RuntimeException('Client projection mismatch.');
            }
        }
    }
    foreach ($plan['accepted'] as $item) {
        $actual = DB::table('legacy_trial_client_profiles')->where('client_id', $item['client']['id'])->value('fields');
        if (json_decode($actual, true, 512, JSON_THROW_ON_ERROR) !== $item['profile']) {
            throw new RuntimeException('Client profile field loss.');
        }
    }
    $reasons = [];
    foreach ($plan['quarantine'] as $item) {
        foreach ($item['reasons'] as $reason) {
            $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
        }
    }
    $countsAfter = $operationalCounts();
    if ($countsBefore !== $countsAfter) {
        throw new RuntimeException('Operational counts changed during rehearsal; investigate concurrent activity.');
    }
    $summary = ['created_at_utc' => gmdate('c'), 'trial_database' => $trial, 'ready_for_production' => false,
        'scope' => 'Lossless 11-table archive and conservative client projection into current MySQL schema. No operational loans/payments/status reconstruction.',
        'live_sources_verified' => $liveVerification, 'runs' => $runs,
        'raw_rows' => DB::table('legacy_trial_rows')->count(), 'clients_imported' => count($projected),
        'profiles_verified' => count($projected), 'clients_quarantined' => count($plan['quarantine']), 'quarantine_reasons' => $reasons,
        'branches' => count($branches), 'client_fields_round_trip_verified' => true,
        'operational_counts_unchanged' => true, 'operational_database_writes_by_this_tool' => 0,
        'limitations' => ['Historical client flags remain in the raw archive; projected status is trial-only prospecto/baja.', 'No duplicate CURP merge. No promoter account or role guessed. No historical interest, arrears, guarantee or balance inferred.', 'Complete original profiles are preserved in a sidecar; application/workflow transformation is pending.', 'No notifications, credentials or documents issued. MySQL migrations may seed permission catalogs inside the trial only.', 'Counts do not prove no concurrent in-place changes in the operational database. Sources verified at the read-only snapshot time.']];
    file_put_contents($reportDirectory.'/summary.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    file_put_contents($reportDirectory.'/quarantine.json', json_encode($plan['quarantine'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode(array_intersect_key($summary, array_flip(['trial_database', 'raw_rows', 'clients_imported', 'clients_quarantined', 'operational_counts_unchanged'])), JSON_PRETTY_PRINT).PHP_EOL;
} catch (Throwable $e) {
    if (isset($reader) && $reader->inTransaction()) {
        $reader->rollBack();
    }
    $failure = ['phase' => $phase, 'error_class' => get_class($e), 'trial_database' => $trial ?? null, 'ready_for_production' => false];
    if (isset($reportDirectory)) {
        file_put_contents($reportDirectory.'/failure.json', json_encode($failure, JSON_PRETTY_PRINT));
    }
    // Never print database exception values: they may contain personal data or credentials.
    fwrite(STDERR, 'Rehearsal stopped at '.$phase.' ('.get_class($e).'). No existing database is a write destination.'.PHP_EOL);
    exit(1);
}
