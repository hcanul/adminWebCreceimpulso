<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Actions;

use App\Domains\LegacyMigration\DTOs\SourceSnapshot;
use RuntimeException;

/** Preserves source rows and provenance; never writes to the operational database. */
final class StageConsolidationAction
{
    public function execute(array $sources, string $destination, array $sourceMetadata = []): array
    {
        $plan = (new BuildConsolidationPlanAction)->execute($sources);
        if (file_exists($destination) || is_link($destination) || ! is_dir(dirname($destination))) {
            throw new RuntimeException('Destination must be new and its parent must exist.');
        }
        $temporary = $destination.'.partial-'.bin2hex(random_bytes(8));
        if (! mkdir($temporary, 0700)) {
            throw new RuntimeException('Cannot create staging directory.');
        }
        $manifest = ['version' => 1, 'ready_for_import' => false, 'created_at' => gmdate('c'), 'sources' => [], 'files' => []];
        try {
            foreach ($sources as $source) {
                if (! $source instanceof SourceSnapshot) {
                    throw new RuntimeException('Invalid source.');
                }
                $directory = $temporary.'/'.$source->sourceId;
                mkdir($directory, 0700);
                $manifest['sources'][] = ['source_id' => $source->sourceId, 'branches' => $source->branches, 'metadata' => $sourceMetadata[$source->sourceId] ?? []];
                foreach ($source->tables as $table => $rows) {
                    $relative = $source->sourceId.'/'.$table.'.ndjson';
                    $handle = fopen($temporary.'/'.$relative, 'xb');
                    if (! $handle) {
                        throw new RuntimeException('Cannot create table export.');
                    }
                    chmod($temporary.'/'.$relative, 0600);
                    try {
                        foreach ($rows as $row) {
                            $line = json_encode(['source_id' => $source->sourceId, 'table' => $table, 'legacy_id' => (string) $row['id'], 'raw' => $row], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
                            if (fwrite($handle, $line) !== strlen($line)) {
                                throw new RuntimeException('Incomplete staging write.');
                            }
                        }
                    } finally {
                        fclose($handle);
                    }
                    $manifest['files'][] = ['path' => $relative, 'rows' => count($rows), 'bytes' => filesize($temporary.'/'.$relative), 'sha256' => hash_file('sha256', $temporary.'/'.$relative)];
                }
            }
            foreach (['plan.json' => $plan, 'manifest.json' => $manifest] as $name => $data) {
                $json = json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                if (file_put_contents($temporary.'/'.$name, $json, LOCK_EX) !== strlen($json)) {
                    throw new RuntimeException('Incomplete report write.');
                }
                chmod($temporary.'/'.$name, 0600);
            }
            if (! rename($temporary, $destination)) {
                throw new RuntimeException('Cannot publish staging directory.');
            }
        } catch (\Throwable $e) {
            // Only this run's private temporary files are removed.
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($temporary, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $entry) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($temporary);
            throw $e;
        }

        return ['structural_checks_passed' => $plan['structural_checks_passed'], 'ready_for_import' => false, 'source_count' => count($sources), 'file_count' => count($manifest['files'])];
    }
}
