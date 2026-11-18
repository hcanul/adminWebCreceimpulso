<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\LegacyMigration\Actions\BuildConsolidationPlanAction;
use App\Domains\LegacyMigration\Actions\PlanTrialClientsAction;
use App\Domains\LegacyMigration\Actions\ReconcileLegacyPaymentsAction;
use App\Domains\LegacyMigration\Actions\RehearseRawImportAction;
use App\Domains\LegacyMigration\Actions\StageConsolidationAction;
use App\Domains\LegacyMigration\DTOs\SourceSnapshot;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LegacyRehearsalTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/crece-rehearsal-'.bin2hex(random_bytes(6));
        mkdir($this->root, 0700);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }

    private function stage(string $source, string $name, string $amount = '00100.20'): string
    {
        $tables = array_fill_keys(BuildConsolidationPlanAction::TABLES, []);
        $tables['demands'] = [['id' => '1', 'curp' => 'FICTICIA', 'unknown' => 'ñ '.$name, 'money' => $amount, 'deleted_at' => null]];
        (new StageConsolidationAction)->execute([new SourceSnapshot($source, [], $tables)], $this->root.'/'.$name);

        return $this->root.'/'.$name;
    }

    public function test_round_trip_idempotence_and_source_separation(): void
    {
        $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $action = new RehearseRawImportAction;
        $action->createSchema($db);
        $a = $this->stage('carrillo', 'a');
        $b = $this->stage('tulum', 'b');
        self::assertSame(1, $action->execute($db, $a)['inserted']);
        self::assertSame(1, $action->execute($db, $b)['inserted']);
        $again = $action->execute($db, $a);
        self::assertSame(0, $again['inserted']);
        self::assertSame(1, $again['unchanged']);
        self::assertSame(1, $again['verified']);
        self::assertSame(2, (int) $db->query('SELECT COUNT(DISTINCT id) FROM legacy_trial_rows')->fetchColumn());
        $row = json_decode($db->query("SELECT payload FROM legacy_trial_rows WHERE source_id = 'carrillo'")->fetchColumn(), true);
        self::assertSame('00100.20', $row['money']);
        self::assertSame('ñ a', $row['unknown']);
        self::assertNull($row['deleted_at']);
    }

    public function test_changed_source_rejects_without_overwriting(): void
    {
        $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $action = new RehearseRawImportAction;
        $action->createSchema($db);
        $action->execute($db, $this->stage('carrillo', 'a'));
        try {
            $action->execute($db, $this->stage('carrillo', 'changed', '99.00'));
            self::fail('Changed source accepted.');
        } catch (RuntimeException) {
            self::assertFalse($db->inTransaction());
            self::assertSame(1, (int) $db->query('SELECT COUNT(*) FROM legacy_trial_rows')->fetchColumn());
            self::assertSame('00100.20', json_decode($db->query('SELECT payload FROM legacy_trial_rows')->fetchColumn(), true)['money']);
        }
    }

    public function test_corrupted_archive_rolls_back(): void
    {
        $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $action = new RehearseRawImportAction;
        $action->createSchema($db);
        $path = $this->stage('carrillo', 'a');
        file_put_contents($path.'/carrillo/demands.ndjson', 'corrupted');
        try {
            $action->execute($db, $path);
            self::fail('Corrupt archive accepted.');
        } catch (RuntimeException) {
            self::assertSame(0, (int) $db->query('SELECT COUNT(*) FROM legacy_trial_rows')->fetchColumn());
        }
    }

    public function test_clients_keep_profiles_and_quarantine_all_ambiguous_identities(): void
    {
        $base = ['id' => '1', 'curp' => 'GOMA900101MQNRRR01', 'city_id' => '1', 'pNombre' => 'María', 'aPaterno' => 'Prueba', 'dudcp' => '00123'];
        $records = [
            ['source_id' => 'a', 'raw' => $base],
            ['source_id' => 'b', 'raw' => $base],
            ['source_id' => 'a', 'raw' => array_replace($base, ['id' => '2', 'curp' => 'LOPA900101MQNRRR02', 'deleted_at' => '2020-01-01 00:00:00'])],
            ['source_id' => 'a', 'raw' => array_replace($base, ['id' => '3', 'curp' => 'LOPA900101MQNRRR03', 'city_id' => '2'])],
            ['source_id' => 'a', 'raw' => array_replace($base, ['id' => '4', 'curp' => 'BAD'])],
            ['source_id' => 'a', 'raw' => array_replace($base, ['id' => '5', 'curp' => 'LOPA900101MQNRRR05', 'updated_at' => '2026-01-01 99:00:00'])],
        ];
        $plan = (new PlanTrialClientsAction)->execute($records, ['a' => ['1' => 'branch1', '2' => 'branch2'], 'b' => ['1' => 'branch1']], ['a' => '1', 'b' => '1']);
        self::assertCount(1, $plan['accepted']);
        self::assertCount(5, $plan['quarantine']);
        self::assertSame('00123', $plan['accepted'][0]['profile']['dudcp']);
        self::assertSame('baja', $plan['accepted'][0]['client']['status']);
        self::assertSame('María', $plan['accepted'][0]['client']['first_name']);
        self::assertSame(['DUPLICATE_CURP_REVIEW'], $plan['quarantine'][0]['reasons']);
        self::assertSame(['BRANCH_OWNERSHIP_REVIEW'], $plan['quarantine'][2]['reasons']);
        self::assertSame(['INVALID_TIMESTAMP'], $plan['quarantine'][4]['reasons']);
    }

    public function test_financial_comparison_uses_exact_cents_and_separates_missing_deleted_and_invalid(): void
    {
        $tables = ['credits' => [['id' => '1', 'deleted_at' => '2020-01-01']],
            'credit_groups' => [['id' => '1', 'credit_id' => '1', 'demand_id' => '1']],
            'charges' => [
                ['id' => '1', 'credit_id' => '1', 'pagoTotal' => '0.30'],
                ['id' => '2', 'credit_id' => '99', 'pagoTotal' => '10.00'],
                ['id' => '3', 'credit_id' => '1', 'pagoTotal' => '1.00'],
                ['id' => '4', 'credit_id' => '1', 'pagoTotal' => '50.00', 'deleted_at' => '2020-01-01'],
            ], 'billing_details' => [
                ['id' => '1', 'charge_id' => '1', 'credit_group_id' => '1', 'demand_id' => '1', 'pago' => '0.10'],
                ['id' => '2', 'charge_id' => '1', 'credit_group_id' => '1', 'demand_id' => '1', 'pago' => '0.20'],
                ['id' => '3', 'charge_id' => '2', 'credit_group_id' => '99', 'demand_id' => '1', 'pago' => '7.00'],
                ['id' => '4', 'charge_id' => '3', 'credit_group_id' => '1', 'demand_id' => '1', 'pago' => '-1.00'],
                ['id' => '5', 'charge_id' => '99', 'credit_group_id' => '1', 'demand_id' => '1', 'pago' => '5.00'],
                ['id' => '6', 'charge_id' => '4', 'credit_group_id' => '1', 'demand_id' => '1', 'pago' => '50.00', 'deleted_at' => '2020-01-01'],
            ]];
        $report = (new ReconcileLegacyPaymentsAction)->execute($tables);
        self::assertSame(1, $report['headers_matched']);
        self::assertSame(1, $report['headers_mismatched']);
        self::assertSame(1, $report['headers_blocked_by_invalid_money']);
        self::assertSame(1, $report['headers_deleted_not_compared']);
        self::assertSame('12.30', $report['totals_mxn']['details_not_deleted']);
        self::assertSame('5.00', $report['totals_mxn']['details_without_header']);
        self::assertSame('10.00', $report['totals_mxn']['headers_without_credit']);
        self::assertSame(2, $report['reference_classifications']['header_not_deleted_to_credit_present_deleted']);
        $differences = array_values(array_filter($report['issues'], fn ($issue) => $issue['code'] === 'PAYMENT_TOTAL_MISMATCH'));
        self::assertSame('3.00', $differences[0]['header_minus_details']);
    }
}
