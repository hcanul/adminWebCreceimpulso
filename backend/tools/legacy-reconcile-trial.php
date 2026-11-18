<?php

declare(strict_types=1);

use App\Domains\LegacyMigration\Actions\ReconcileLegacyPaymentsAction;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';

try {
    umask(0077);
    if ($argc !== 3 || ! is_file($argv[1]) || file_exists($argv[2]) || ! is_dir(dirname($argv[2]))) {
        throw new RuntimeException('Invalid input.');
    }
    $database = trim(file_get_contents($argv[1]));
    if (! preg_match('/^crece_migration_trial_[0-9]{8}_[0-9]{6}_[a-f0-9]{6}$/D', $database)) {
        throw new RuntimeException('Only rehearsal databases are supported.');
    }
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $config = config('database.connections.mysql');
    if (! in_array($config['host'], ['127.0.0.1', 'localhost', '::1'], true) || ! empty($config['url'])) {
        throw new RuntimeException('Local MySQL required.');
    }
    $pdo = new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$database.';charset=utf8mb4', $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_STRINGIFY_FETCHES => true]);
    $pdo->exec('SET SESSION TRANSACTION READ ONLY');
    $pdo->beginTransaction();
    $sources = $pdo->query('SELECT DISTINCT source_id FROM legacy_trial_rows ORDER BY source_id')->fetchAll(PDO::FETCH_COLUMN);
    $result = ['database' => $database, 'ready_for_financial_import' => false, 'sources' => []];
    $query = $pdo->prepare('SELECT payload FROM legacy_trial_rows WHERE source_id = ? AND table_name = ? ORDER BY legacy_id');
    foreach ($sources as $sid) {
        $tables = [];
        foreach (['credits', 'charges', 'billing_details', 'credit_groups'] as $table) {
            $query->execute([$sid, $table]);
            $tables[$table] = [];
            while (($payload = $query->fetchColumn()) !== false) {
                $tables[$table][] = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            }
        }
        $result['sources'][$sid] = (new ReconcileLegacyPaymentsAction)->execute($tables);
        unset($tables);
    }
    $pdo->rollBack();
    $file = fopen($argv[2], 'xb');
    fwrite($file, json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    fclose($file);
    echo 'Conciliación de cabeceras/detalles generada para '.count($sources).' fuentes. Sin cambios en registros.'.PHP_EOL;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Reconciliation stopped ('.get_class($e).'). Usage: php tools/legacy-reconcile-trial.php database.txt NEW_REPORT.json'.PHP_EOL);
    exit(1);
}
