<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Actions;

use PDO;
use RuntimeException;

/** Lossless rehearsal archive, not an operational financial importer. */
final class RehearseRawImportAction
{
    public function createSchema(PDO $db): void
    {
        $db->exec('CREATE TABLE legacy_trial_rows (id VARCHAR(36) PRIMARY KEY, source_id VARCHAR(64) NOT NULL, table_name VARCHAR(40) NOT NULL, legacy_id VARCHAR(32) NOT NULL, payload LONGTEXT NOT NULL, checksum VARCHAR(64) NOT NULL, UNIQUE(source_id, table_name, legacy_id))');
    }

    public function execute(PDO $db, string $directory): array
    {
        $manifest = json_decode(file_get_contents($directory.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $sid = $manifest['sources'][0]['source_id'] ?? '';
        if (count($manifest['sources'] ?? []) !== 1 || ! preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $sid)) {
            throw new RuntimeException('One explicitly identified source per staging directory is required.');
        }
        $db->beginTransaction();
        try {
            $report = ['source_id' => $sid, 'inserted' => 0, 'unchanged' => 0, 'verified' => 0, 'tables' => []];
            $seenTables = [];
            foreach ($manifest['files'] as $file) {
                $table = basename($file['path'], '.ndjson');
                if (! in_array($table, BuildConsolidationPlanAction::TABLES, true) || isset($seenTables[$table]) || $file['path'] !== $sid.'/'.$table.'.ndjson') {
                    throw new RuntimeException('Invalid or repeated staging path.');
                }
                $seenTables[$table] = true;
                $path = $directory.'/'.$file['path'];
                if (is_link($path) || ! hash_equals($file['sha256'], hash_file('sha256', $path))) {
                    throw new RuntimeException('Staging hash mismatch.');
                }
                $existingQuery = $db->prepare('SELECT legacy_id, checksum FROM legacy_trial_rows WHERE source_id = ? AND table_name = ?');
                $existingQuery->execute([$sid, $table]);
                $existing = $existingQuery->fetchAll(PDO::FETCH_KEY_PAIR);
                $insert = $db->prepare('INSERT INTO legacy_trial_rows (id, source_id, table_name, legacy_id, payload, checksum) VALUES (?, ?, ?, ?, ?, ?)');
                $handle = fopen($path, 'rb');
                $seen = [];
                $digest = hash_init('sha256');
                try {
                    while (($line = fgets($handle)) !== false) {
                        $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                        $id = (string) ($row['legacy_id'] ?? '');
                        if ($row['source_id'] !== $sid || $row['table'] !== $table || ! preg_match('/^[1-9][0-9]*$/D', $id) || (string) ($row['raw']['id'] ?? '') !== $id || isset($seen[$id])) {
                            throw new RuntimeException('Invalid or duplicate source identity.');
                        }
                        $seen[$id] = true;
                        $payload = json_encode($row['raw'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        $hash = hash('sha256', $payload);
                        if (isset($existing[$id])) {
                            if (! hash_equals($existing[$id], $hash)) {
                                throw new RuntimeException('A previously imported source record changed; no overwrite allowed.');
                            }
                            $report['unchanged']++;
                        } else {
                            $insert->execute([(new BuildConsolidationPlanAction)->candidateId($sid, $table, $id), $sid, $table, $id, $payload, $hash]);
                            $report['inserted']++;
                        }
                    }
                    if (! feof($handle)) {
                        throw new RuntimeException('Incomplete staging read.');
                    }
                } finally {
                    fclose($handle);
                }
                if (count($seen) !== $file['rows'] || array_diff_key($existing, $seen) !== []) {
                    throw new RuntimeException('Source count mismatch or missing previously imported records.');
                }
                // Compare database round-trip values to source, not just the stored checksum column.
                $read = $db->prepare('SELECT legacy_id, payload, checksum FROM legacy_trial_rows WHERE source_id = ? AND table_name = ? ORDER BY legacy_id');
                $read->execute([$sid, $table]);
                $databaseHashes = [];
                while ($row = $read->fetch(PDO::FETCH_ASSOC)) {
                    $hash = hash('sha256', $row['payload']);
                    if (! hash_equals($row['checksum'], $hash)) {
                        throw new RuntimeException('Database round-trip checksum mismatch.');
                    }
                    $databaseHashes[$row['legacy_id']] = $hash;
                }
                $handle = fopen($path, 'rb');
                try {
                    while (($line = fgets($handle)) !== false) {
                        $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                        $hash = hash('sha256', json_encode($row['raw'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                        if (($databaseHashes[$row['legacy_id']] ?? null) !== $hash) {
                            throw new RuntimeException('Source/database payload mismatch.');
                        }
                        hash_update($digest, $row['legacy_id'].':'.$hash."\n");
                        $report['verified']++;
                    }
                } finally {
                    fclose($handle);
                }
                $report['tables'][$table] = ['rows' => count($seen), 'round_trip_verified' => true, 'content_sha256' => hash_final($digest)];
            }
            if (array_diff(BuildConsolidationPlanAction::TABLES, array_keys($seenTables)) !== []) {
                throw new RuntimeException('Missing staging tables.');
            }
            $db->commit();

            return $report;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
