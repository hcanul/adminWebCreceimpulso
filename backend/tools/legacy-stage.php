<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';
use App\Domains\LegacyMigration\Actions\StageConsolidationAction;
use App\Domains\LegacyMigration\DTOs\SourceSnapshot;

try {
    if ($argc !== 3 || ! is_file($argv[1]) || filesize($argv[1]) > 50 * 1024 * 1024) {
        throw new InvalidArgumentException('Invalid input.');
    }
    $data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
    if (! is_array($data['sources'] ?? null) || ! array_is_list($data['sources'])) {
        throw new InvalidArgumentException('Expected source list.');
    }
    $sources = array_map(fn ($s) => new SourceSnapshot($s['source_id'], $s['branches'], $s['tables']), $data['sources']);
    $metadata = [];
    foreach ($data['sources'] as $source) {
        $metadata[$source['source_id']] = array_diff_key($source, array_flip(['source_id', 'branches', 'tables']));
    }
    $result = (new StageConsolidationAction)->execute($sources, $argv[2], $metadata);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
    exit($result['structural_checks_passed'] ? 0 : 2);
} catch (Throwable) {
    fwrite(STDERR, "Staging rejected. Usage: php tools/legacy-stage.php snapshot.json NEW_DIRECTORY. Pilot limit 50 MiB; parent directory must exist. No operational database was modified.\n");
    exit(1);
}
