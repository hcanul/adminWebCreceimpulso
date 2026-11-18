<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\LegacyMigration\Actions\StageConsolidationAction;
use App\Domains\LegacyMigration\DTOs\SourceSnapshot;
use PHPUnit\Framework\TestCase;

final class LegacyStagingTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/crece-stage-test-'.bin2hex(random_bytes(8));
        mkdir($this->root, 0700);
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $p) {
            $p->isDir() ? rmdir($p->getPathname()) : unlink($p->getPathname());
        }rmdir($this->root);
    }

    public function test_preserves_rows_and_separates_identical_ids_across_sources(): void
    {
        $sources = [];
        foreach (['carrillo', 'tulum'] as $id) {
            $sources[] = new SourceSnapshot($id, [], ['demands' => [['id' => '1', 'curp' => 'FICTICIA', 'importe' => '1000.20', 'campo_desconocido' => 'Conservar ñ']]]);
        }
        $result = (new StageConsolidationAction)->execute($sources, $this->root.'/stage');
        $this->assertFalse($result['ready_for_import']);
        $this->assertFalse($result['structural_checks_passed']);
        $manifest = json_decode(file_get_contents($this->root.'/stage/manifest.json'), true);
        $this->assertCount(2, $manifest['files']);
        foreach ($manifest['files'] as $f) {
            $file = $this->root.'/stage/'.$f['path'];
            $row = json_decode(trim(file_get_contents($file)), true);
            $this->assertSame('1000.20', $row['raw']['importe']);
            $this->assertSame('Conservar ñ', $row['raw']['campo_desconocido']);
            $this->assertSame($f['sha256'], hash_file('sha256', $file));
            $this->assertSame(0600, fileperms($file) & 0777);
        }
    }

    public function test_exporter_rejects_remote_hosts_without_leaking_credentials_or_partial_files(): void
    {
        $config = $this->root.'/sources.json';
        file_put_contents($config, json_encode(['sources' => [['source_id' => 'a', 'branches' => [], 'database' => 'legacy_demo', 'host' => '8.8.8.8']]]));
        $output = $this->root.'/snapshot.json';
        $process = proc_open([PHP_BINARY, dirname(__DIR__, 2).'/tools/legacy-export.php', $config, $output], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, array_merge(getenv(), ['LEGACY_DB_USER' => 'demo', 'LEGACY_DB_PASSWORD' => 'SENSITIVE_TEST_VALUE']));
        self::assertIsResource($process);
        fclose($pipes[0]);
        $text = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(1, proc_close($process));
        self::assertStringNotContainsString('SENSITIVE_TEST_VALUE', $text);
        self::assertFileDoesNotExist($output);
        self::assertSame([], glob($output.'.partial-*'));
    }

    public function test_never_replaces_an_existing_destination(): void
    {
        mkdir($this->root.'/existing');
        $this->expectException(\RuntimeException::class);
        (new StageConsolidationAction)->execute([new SourceSnapshot('a', [], [])], $this->root.'/existing');
    }
}
