<?php

namespace Tests\Unit;

use App\Domains\LegacyMigration\Actions\PlanCajasFollowupAction;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class CajasFollowupPlanTest extends TestCase
{
    private function fixture(): array
    {
        $branch = Uuid::uuid4()->toString();
        $member = ['source_id' => 'carrillo', 'legacy_credit_group_id' => 5, 'legacy_credit_id' => 10, 'legacy_demand_id' => 9, 'branch_id' => $branch, 'loan_application_id' => Uuid::uuid4()->toString(), 'loan_participation_id' => Uuid::uuid4()->toString(), 'client_id' => Uuid::uuid4()->toString()];
        $entry = ['id' => 1, 'database_connection_id' => 1, 'credit_group_id' => 5, 'credit_id' => 10, 'demand_id' => 9, 'user_id' => 7, 'author_name' => 'Autora histórica', 'observation' => 'Visita realizada, ñ.', 'created_at' => '2026-10-20 10:00:00'];

        return [['tables' => ['database_connections' => [['id' => 1]], 'users' => [['id' => 7, 'name' => 'Nombre actual']], 'payment_observation_entries' => [$entry, [...$entry, 'id' => 2, 'created_at' => '2026-10-21 10:00:00']], 'overdue_payment_notes' => [['id' => 1, 'database_connection_id' => 1, 'credit_group_id' => 5, 'updated_by' => 7, 'observation' => $entry['observation'], 'updated_at' => '2026-10-21 10:00:00']]]], ['branches' => [['central_branch_id' => 1, 'source_id' => 'carrillo', 'branch_id' => $branch]], 'participations' => [$member]]];
    }

    public function test_preserves_repeated_notes_and_source_author_without_reimporting_current_cache(): void
    {
        [$snapshot, $mapping] = $this->fixture();
        $first = (new PlanCajasFollowupAction)->execute($snapshot, $mapping);
        $second = (new PlanCajasFollowupAction)->execute($snapshot, $mapping);
        self::assertFalse($first['ready_for_import']);
        self::assertSame(2, $first['planned_count']);
        self::assertSame($first['entries'], $second['entries']);
        self::assertNotSame($first['entries'][0]['id'], $first['entries'][1]['id']);
        self::assertSame('Autora histórica', $first['entries'][0]['actor_name']);
        self::assertNull($first['entries'][0]['actor_id']);
        self::assertSame('Visita realizada, ñ.', $first['entries'][0]['observation']);
    }

    public function test_never_guesses_relations_from_local_numeric_ids(): void
    {
        [$snapshot, $mapping] = $this->fixture();
        $mapping['branches'][0]['source_id'] = 'tulum';
        $plan = (new PlanCajasFollowupAction)->execute($snapshot, $mapping);
        self::assertSame(0, $plan['planned_count']);
        self::assertSame(3, $plan['quarantine_count']);
        self::assertSame('unmapped_participation', $plan['quarantine'][0]['reason']);
        [$snapshot, $mapping] = $this->fixture();
        $snapshot['tables']['payment_observation_entries'][0]['credit_id'] = 99;
        $plan = (new PlanCajasFollowupAction)->execute($snapshot, $mapping);
        self::assertSame('relationship_mismatch', $plan['quarantine'][0]['reason']);
    }

    public function test_invalid_dates_remain_in_quarantine_with_original_content(): void
    {
        [$snapshot, $mapping] = $this->fixture();
        $snapshot['tables']['payment_observation_entries'][0]['created_at'] = '2026-02-30 10:00:00';
        $plan = (new PlanCajasFollowupAction)->execute($snapshot, $mapping);
        self::assertSame('invalid_timestamp', $plan['quarantine'][0]['reason']);
        self::assertSame('Visita realizada, ñ.', $plan['quarantine'][0]['raw']['observation']);
    }
}
