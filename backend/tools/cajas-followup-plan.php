<?php

declare(strict_types=1);
use App\Domains\LegacyMigration\Actions\PlanCajasFollowupAction;

require dirname(__DIR__).'/vendor/autoload.php';

try {
    if ($argc !== 4 || file_exists($argv[3]) || is_link($argv[3]) || ! is_dir(dirname($argv[3]))) {
        throw new RuntimeException('New private output required.');
    }
    foreach ([$argv[1], $argv[2]] as $file) {
        if (! is_file($file) || filesize($file) > 50 * 1024 * 1024) {
            throw new RuntimeException('Invalid input file.');
        }
    }
    $snapshot = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
    $mapping = json_decode(file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
    $plan = (new PlanCajasFollowupAction)->execute($snapshot, $mapping);
    $handle = fopen($argv[3], 'xb');
    if (! $handle) {
        throw new RuntimeException('Cannot create private output.');
    }
    chmod($argv[3], 0600);
    $json = json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (fwrite($handle, $json) !== strlen($json)) {
        throw new RuntimeException('Incomplete output.');
    }
    fclose($handle);
    echo json_encode(['ready_for_import' => false, 'planned_count' => $plan['planned_count'], 'quarantine_count' => $plan['quarantine_count']], JSON_THROW_ON_ERROR).PHP_EOL;
    exit(0);
} catch (Throwable) {
    fwrite(STDERR, "Plan rejected. Usage: php tools/cajas-followup-plan.php CENTRAL_SNAPSHOT.json VERIFIED_MAPPING.json NEW_PRIVATE_PLAN.json. No database was changed.\n");
    exit(1);
}
