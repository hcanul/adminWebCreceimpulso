<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\LegacyMigration\Actions\BuildConsolidationPlanAction as Plan;
use App\Domains\LegacyMigration\DTOs\SourceSnapshot;
use PHPUnit\Framework\TestCase;

final class LegacyConsolidationTest extends TestCase
{
    private function source(string $name, array $overrides = []): SourceSnapshot
    {
        $tables = array_fill_keys(Plan::TABLES, []);

        return new SourceSnapshot($name, ['1' => '706a4a62-f9b9-4b30-94db-5a3522ca18ed'], array_replace($tables, ['citys' => [['id' => 1]]], $overrides));
    }

    public function test_identity_is_stable_and_separates_databases_and_tables(): void
    {
        $p = new Plan;
        self::assertSame($p->candidateId('a', 'demands', '1'), $p->candidateId('a', 'demands', '1'));
        self::assertNotSame($p->candidateId('a', 'demands', '1'), $p->candidateId('b', 'demands', '1'));
        self::assertNotSame($p->candidateId('a', 'demands', '1'), $p->candidateId('a', 'credits', '1'));
    }

    public function test_missing_tables_and_unmapped_branches_are_not_silently_accepted(): void
    {
        $r = (new Plan)->execute([new SourceSnapshot('a', [], ['citys' => [['id' => 1]]])]);
        self::assertContains('MISSING_TABLE', array_column($r['issues'], 'code'));
        self::assertContains('UNMAPPED_BRANCH', array_column($r['issues'], 'code'));
        self::assertFalse($r['ready_for_import']);
    }

    public function test_foreign_keys_cannot_resolve_against_another_source(): void
    {
        $r = (new Plan)->execute([$this->source('a', ['charges' => [['id' => 1, 'credit_id' => 9, 'pagoTotal' => '0.00']]]), $this->source('b', ['credits' => [['id' => 9]]])]);
        self::assertContains('MISSING_REFERENCE', array_column($r['issues'], 'code'));
    }

    public function test_duplicate_source_fails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Plan)->execute([$this->source('a'), $this->source('a')]);
    }

    public function test_curp_collision_requires_review_without_exposing_curp(): void
    {
        $rows = ['demands' => [['id' => 1, 'curp' => 'GODE561231HDFRRN09']]];
        $r = (new Plan)->execute([$this->source('a', $rows), $this->source('b', $rows)]);
        self::assertCount(1, $r['identity_reviews']);
        self::assertStringNotContainsString('GODE561231HDFRRN09', json_encode($r));
    }

    public function test_payment_details_must_match_header_and_credit(): void
    {
        $r = (new Plan)->execute([$this->source('a', [
            'charges' => [['id' => 1, 'credit_id' => 2, 'pagoTotal' => '10.00']],
            'credit_groups' => [['id' => 1, 'credit_id' => 3, 'demand_id' => 4]],
            'billing_details' => [['id' => 1, 'charge_id' => 1, 'credit_group_id' => 1, 'demand_id' => 4, 'pago' => '9.99']],
        ])]);
        self::assertContains('PAYMENT_TOTAL_MISMATCH', array_column($r['issues'], 'code'));
        self::assertContains('PAYMENT_RELATION_MISMATCH', array_column($r['issues'], 'code'));
    }

    public function test_decimal_strings_are_exact_and_floats_are_rejected(): void
    {
        $r = (new Plan)->execute([$this->source('a', [
            'charges' => [['id' => 1, 'credit_id' => 2, 'pagoTotal' => '0.30'], ['id' => 2, 'credit_id' => 2, 'pagoTotal' => 0.3]],
            'billing_details' => [['id' => 1, 'charge_id' => 1, 'credit_group_id' => 1, 'demand_id' => 1, 'pago' => '0.10'], ['id' => 2, 'charge_id' => 1, 'credit_group_id' => 1, 'demand_id' => 1, 'pago' => '0.20']],
        ])]);
        self::assertNotContains('PAYMENT_TOTAL_MISMATCH', array_column($r['issues'], 'code'));
        self::assertContains('INVALID_MONEY_REQUIRES_REVIEW', array_column($r['issues'], 'code'));
    }

    public function test_duplicate_ids_and_mixed_id_representations_are_detected(): void
    {
        $r = (new Plan)->execute([$this->source('a', [
            'credit_groups' => [
                ['id' => 1, 'credit_id' => 2, 'demand_id' => 3],
                ['id' => 2, 'credit_id' => '2', 'demand_id' => '3'],
                ['id' => 2, 'credit_id' => 2, 'demand_id' => 4],
            ],
        ])]);
        self::assertContains('DUPLICATE_PARTICIPATION', array_column($r['issues'], 'code'));
        self::assertContains('DUPLICATE_ID', array_column($r['issues'], 'code'));
    }

    public function test_clean_structure_is_still_not_authorization_to_import(): void
    {
        $r = (new Plan)->execute([$this->source('a')]);
        self::assertTrue($r['structural_checks_passed']);
        self::assertFalse($r['ready_for_import']);
    }
}
