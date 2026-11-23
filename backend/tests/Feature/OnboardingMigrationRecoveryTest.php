<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class OnboardingMigrationRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): \Illuminate\Database\Migrations\Migration
    {
        return require database_path('migrations/2026_11_23_010000_create_user_onboarding_visits.php');
    }

    public function test_first_install_excludes_existing_staff_but_enables_new_users(): void
    {
        $existing = User::factory()->create();
        $migration = $this->migration();
        $migration->down();

        $migration->up();

        $this->assertFalse((bool) $existing->fresh()->admin_onboarding_enabled);
        $this->assertTrue((bool) User::factory()->create()->fresh()->admin_onboarding_enabled);
        $this->assertTrue(Schema::hasTable('user_onboarding_visits'));
    }

    public function test_retry_with_existing_column_preserves_user_preferences_and_creates_visits(): void
    {
        $enabled = User::factory()->create(['admin_onboarding_enabled' => true]);
        $disabled = User::factory()->create(['admin_onboarding_enabled' => false]);
        Schema::drop('user_onboarding_visits');

        $this->migration()->up();

        $this->assertTrue((bool) $enabled->fresh()->admin_onboarding_enabled);
        $this->assertFalse((bool) $disabled->fresh()->admin_onboarding_enabled);
        $this->assertTrue(Schema::hasTable('user_onboarding_visits'));
    }

    public function test_retry_after_table_creation_preserves_recorded_visits(): void
    {
        $user = User::factory()->create(['admin_onboarding_enabled' => true]);
        $visit = [
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'tour' => 'admin-web',
            'login_number' => 1,
            'token_id' => null,
            'started_at' => now()->toDateTimeString(),
            'created_at' => now()->toDateTimeString(),
        ];
        DB::table('user_onboarding_visits')->insert($visit);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertDatabaseHas('user_onboarding_visits', $visit);
        $this->assertDatabaseCount('user_onboarding_visits', 1);
        $this->assertTrue((bool) $user->fresh()->admin_onboarding_enabled);
    }
}
