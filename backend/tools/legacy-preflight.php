<?php

declare(strict_types=1);

// Deliberately do not bootstrap Laravel: no .env, production database, migrations or seeders.
require dirname(__DIR__).'/vendor/autoload.php';

use App\Domains\LegacyMigration\Actions\BuildConsolidationPlanAction;
use App\Domains\LegacyMigration\DTOs\SourceSnapshot;

try {
    if ($argc !== 2 || ! is_file($argv[1])) {
        throw new InvalidArgumentException('Usage: php tools/legacy-preflight.php /path/to/snapshot.json');
    }
    if (filesize($argv[1]) > 50 * 1024 * 1024) {
        throw new InvalidArgumentException('Pilot limit: 50 MiB. Use a streamed pipeline for full migration.');
    }
    $data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
    if (! is_array($data['sources'] ?? null) || ! array_is_list($data['sources'])) {
        throw new InvalidArgumentException('Expected sources list.');
    }
    $sources = [];
    foreach ($data['sources'] as $source) {
        if (! is_array($source) || ! is_string($source['source_id'] ?? null) || ! is_array($source['branches'] ?? null) || ! is_array($source['tables'] ?? null)) {
            throw new InvalidArgumentException('Invalid source envelope.');
        }
        $sources[] = new SourceSnapshot($source['source_id'], $source['branches'], $source['tables']);
    }
    $report = (new BuildConsolidationPlanAction)->execute($sources);
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
    exit($report['structural_checks_passed'] ? 0 : 2);
} catch (Throwable $error) {
    // Do not echo arbitrary input or stack traces containing exported customer data.
    fwrite(STDERR, 'Preflight rejected the input. Check the documented format, IDs and file size.'.PHP_EOL);
    exit(1);
}
