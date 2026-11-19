<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\LegacyMigration\Actions\ApplyBranchClientsTrialAction;
use App\Domains\LegacyMigration\Actions\BuildBranchClientPlanAction;
use App\Domains\LegacyMigration\Services\BranchTrialSchema;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Tests\TestCase;

final class BranchClientRehearsalTest extends TestCase
{
    use RefreshDatabase;

    private string $marker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->marker = Uuid::uuid4()->toString();
        (new BranchTrialSchema)->create($this->marker);
    }

    private function snapshot(string $sid = 'creceimp_creceimpulso', string $curp = 'GOMA900101MQNRRR01'): array
    {
        return ['source_id' => $sid, 'source_database' => 'restored_backup', 'source_timezone' => 'America/Cancun', 'source_timezone_confirmed' => true,
            'branch' => ['id' => Uuid::uuid5(Uuid::NAMESPACE_URL, 'branch:'.$sid)->toString(), 'name' => 'Sucursal de prueba', 'legacy_city_id' => '1'],
            'demands' => [['id' => '1', 'curp' => $curp, 'city_id' => '1', 'pNombre' => 'María', 'sNombre' => 'Elena', 'aPaterno' => 'Prueba', 'aMaterno' => 'López', 'dudtelefono' => '0000000000', 'dudcalle' => 'Calle de prueba', 'dudcp' => '00123', 'campo_original' => 'Información íntegra ñ', 'created_at' => '2026-11-13 10:00:00', 'updated_at' => null, 'deleted_at' => null]]];
    }

    private function apply(array $snapshot): array
    {
        return (new ApplyBranchClientsTrialAction)->execute((new BuildBranchClientPlanAction)->execute($snapshot), $this->marker);
    }

    public function test_transforms_clients_preserves_profile_and_retries_without_new_folios(): void
    {
        $snapshot = $this->snapshot();
        $first = $this->apply($snapshot);
        self::assertSame(1, $first['inserted']);
        $client = DB::table('clients')->first();
        self::assertSame('María Elena', $client->first_name);
        self::assertSame('Prueba López', $client->last_name);
        self::assertSame('2026-11-13 15:00:00', $client->created_at);
        self::assertSame('prospecto', $client->status);
        self::assertStringStartsWith('CLI-', $client->folio);
        self::assertSame($snapshot['demands'][0], json_decode(DB::table('branch_trial_client_maps')->value('profile'), true));
        $sequences = DB::table('folio_sequences')->orderBy('id')->get()->toArray();
        $again = $this->apply($snapshot);
        self::assertSame(0, $again['inserted']);
        self::assertSame(1, $again['unchanged']);
        self::assertEquals($sequences, DB::table('folio_sequences')->orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('loan_applications', 0);
        $this->assertDatabaseCount('app_messages', 0);
        $this->assertDatabaseCount('client_accounts', 0);
    }

    public function test_adds_another_branch_without_merging_duplicate_curp_or_reusing_local_id(): void
    {
        $first = $this->snapshot();
        $this->apply($first);
        $second = $this->snapshot('creceimp_tulum');
        $result = $this->apply($second);
        self::assertSame(0, $result['inserted']);
        self::assertSame(1, $result['quarantined']);
        self::assertSame(['CURP_EXISTS_IN_DESTINATION_REVIEW'], $result['issues'][0]['reasons']);
        $third = $this->snapshot('creceimp_morelos', 'LOPA900101MQNRRR02');
        self::assertSame(1, $this->apply($third)['inserted']);
        $this->assertDatabaseCount('clients', 2);
        $this->assertDatabaseCount('branches', 3);
        self::assertCount(2, DB::table('branch_trial_client_maps')->pluck('client_id')->unique());
        self::assertSame($first['branch']['id'], DB::table('clients')->where('curp', $first['demands'][0]['curp'])->value('branch_id'));
    }

    public function test_quarantines_invalid_duplicate_and_wrong_branch_clients_without_loss_in_snapshot(): void
    {
        $snapshot = $this->snapshot();
        $row = $snapshot['demands'][0];
        $snapshot['demands'][] = [...$row, 'id' => '2'];
        $snapshot['demands'][] = [...$row, 'id' => '3', 'curp' => 'INVALID'];
        $snapshot['demands'][] = [...$row, 'id' => '4', 'curp' => 'LOPA900101MQNRRR02', 'city_id' => '2'];
        $plan = (new BuildBranchClientPlanAction)->execute($snapshot);
        self::assertCount(0, $plan['accepted']);
        self::assertCount(4, $plan['quarantine']);
        self::assertSame(4, $plan['source_rows']);
        self::assertSame(4, $this->apply($snapshot)['quarantined']);
        $this->assertDatabaseCount('clients', 0);
    }

    public function test_rejects_destination_changes_and_wrong_marker_without_overwrite(): void
    {
        $snapshot = $this->snapshot();
        $this->apply($snapshot);
        DB::table('clients')->update(['first_name' => 'Corrección posterior']);
        try {
            $this->apply($snapshot);
            self::fail('Destination changed but was overwritten');
        } catch (RuntimeException) {
        }
        self::assertSame('Corrección posterior', DB::table('clients')->value('first_name'));
        try {
            (new ApplyBranchClientsTrialAction)->execute((new BuildBranchClientPlanAction)->execute($snapshot), Uuid::uuid4()->toString());
            self::fail('Wrong marker accepted');
        } catch (RuntimeException) {
        }
        $this->assertDatabaseCount('clients', 1);
    }

    public function test_rejects_changed_snapshot_in_same_lab_and_rolls_back_failed_mapping(): void
    {
        $snapshot = $this->snapshot();
        $this->apply($snapshot);
        $changed = $snapshot;
        $changed['demands'][0]['pNombre'] = 'Otro nombre';
        try {
            $this->apply($changed);
            self::fail('Changed source accepted');
        } catch (RuntimeException) {
        }
        $before = DB::table('folio_sequences')->get()->toArray();
        DB::statement("CREATE TRIGGER reject_trial_map BEFORE INSERT ON branch_trial_client_maps BEGIN SELECT RAISE(ABORT, 'test failure'); END");
        try {
            $this->apply($this->snapshot('creceimp_morelos', 'LOPA900101MQNRRR02'));
            self::fail('Expected failed mapping insert');
        } catch (QueryException) {
        }
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('branches', 1);
        $this->assertDatabaseCount('branch_trial_sources', 1);
        self::assertEquals($before, DB::table('folio_sequences')->get()->toArray());
    }

    public function test_timezone_must_be_explicitly_confirmed(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['source_timezone_confirmed'] = false;
        $this->expectException(\InvalidArgumentException::class);
        (new BuildBranchClientPlanAction)->execute($snapshot);
    }

    public function test_cli_plans_fictitious_snapshot_without_database_and_rejects_remote_source(): void
    {
        $root = sys_get_temp_dir().'/crece-branch-cli-'.bin2hex(random_bytes(6));
        mkdir($root, 0700);
        try {
            $script = dirname(__DIR__, 2).'/tools/branch-client-rehearsal.php';
            $fixture = dirname(__DIR__, 2).'/tools/fixtures/branch-clients-demo.json';
            $process = proc_open([PHP_BINARY, $script, 'plan-snapshot', $fixture, $root.'/plan'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            self::assertSame(0, proc_close($process), $output);
            $plan = json_decode(file_get_contents($root.'/plan/plan.json'), true);
            self::assertCount(1, $plan['accepted']);
            self::assertCount(1, $plan['quarantine']);
            self::assertSame(0600, fileperms($root.'/plan/plan.json') & 0777);
            file_put_contents($root.'/config.json', json_encode(['source' => ['host' => '8.8.8.8', 'database' => 'demo']]));
            $process = proc_open([PHP_BINARY, $script, 'plan', $root.'/config.json', $root.'/remote'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            self::assertSame(1, proc_close($process));
            self::assertDirectoryDoesNotExist($root.'/remote');
            self::assertStringNotContainsString('8.8.8.8', $output);
        } finally {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($root);
        }
    }
}
