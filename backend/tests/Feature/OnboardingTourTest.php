<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class OnboardingTourTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user, ?string $app = 'admin-web', string $password = 'ArtificialPassword123'): string
    {
        app('auth')->forgetGuards();
        $this->withoutToken();
        return $this->postJson('/api/login', [
            'email' => $user->email, 'password' => $password, 'device_name' => 'Artificial onboarding test',
            ...($app ? ['app' => $app] : []),
        ])->assertOk()->json('data.token');
    }

    private function claim(string $token)
    {
        app('auth')->forgetGuards();
        return $this->withToken($token)->postJson('/api/onboarding/admin-web/start');
    }

    private function user(): User
    {
        return User::factory()->create(['is_active' => true, 'password' => 'ArtificialPassword123']);
    }

    public function test_tour_is_available_only_for_first_two_logins_and_reload_does_not_consume_another(): void
    {
        $user = $this->user();
        $first = $this->login($user);
        $this->claim($first)->assertOk()->assertJsonPath('show', true)->assertJsonPath('login_number', 1);
        $this->claim($first)->assertOk()->assertJsonPath('show', false)->assertHeader('Cache-Control', 'no-store, private');
        $second = $this->login($user);
        $this->claim($second)->assertOk()->assertJsonPath('show', true)->assertJsonPath('login_number', 2);
        $third = $this->login($user);
        $this->claim($third)->assertOk()->assertJsonPath('show', false);
        $this->assertDatabaseCount('user_onboarding_visits', 2);
    }

    public function test_count_is_per_user_and_request_cannot_claim_another_users_visit(): void
    {
        $a = $this->user();
        $b = $this->user();
        $first = $this->login($a);
        $other = $this->login($b);
        $this->claim($other)->assertOk()->assertJsonPath('login_number', 1);
        $this->assertDatabaseHas('user_onboarding_visits', ['user_id' => $a->id, 'started_at' => null]);
        $this->claim($first)->assertOk()->assertJsonPath('show', true);
    }

    public function test_invalid_credentials_and_other_apps_do_not_consume_web_logins(): void
    {
        $user = $this->user();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong', 'device_name' => 'Artificial', 'app' => 'admin-web'])->assertUnprocessable();
        $staff = $this->login($user, null);
        $this->claim($staff)->assertOk()->assertJsonPath('show', false);
        $this->assertDatabaseCount('user_onboarding_visits', 0);
        $this->claim($this->login($user))->assertOk()->assertJsonPath('login_number', 1);
    }

    public function test_password_expiry_defers_tour_and_token_renewal_keeps_the_same_login(): void
    {
        $user = $this->user();
        $user->forceFill(['password_changed_at' => now()->subDays(16)])->save();
        $token = $this->login($user);
        $this->claim($token)->assertForbidden()->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
        $result = $this->withToken($token)->postJson('/api/password', [
            'current_password' => 'ArtificialPassword123', 'password' => 'ChangedArtificial456', 'password_confirmation' => 'ChangedArtificial456',
        ])->assertOk();
        $this->claim($result->json('token'))->assertOk()->assertJsonPath('show', true)->assertJsonPath('login_number', 1);
        $this->assertDatabaseCount('user_onboarding_visits', 1);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => explode('|', $token)[0]]);
    }

    public function test_finished_visit_is_not_shown_again_after_password_rotation(): void
    {
        $user = $this->user();
        $token = $this->login($user);
        $this->claim($token)->assertOk()->assertJsonPath('show', true);
        $result = $this->withToken($token)->postJson('/api/password', [
            'current_password' => 'ArtificialPassword123', 'password' => 'ChangedArtificial456', 'password_confirmation' => 'ChangedArtificial456',
        ])->assertOk();
        $this->claim($result->json('token'))->assertOk()->assertJsonPath('show', false);
        $this->claim($this->login($user, 'admin-web', 'ChangedArtificial456'))->assertOk()->assertJsonPath('login_number', 2);
    }

    public function test_unseen_login_still_counts_and_revoking_tokens_preserves_the_limit(): void
    {
        $user = $this->user();
        $this->login($user);
        $user->tokens()->delete();
        $second = $this->login($user);
        $this->claim($second)->assertOk()->assertJsonPath('login_number', 2);
        $user->tokens()->delete();
        $this->claim($this->login($user))->assertOk()->assertJsonPath('show', false);
        $this->assertDatabaseCount('user_onboarding_visits', 2);
    }

    public function test_existing_staff_can_be_excluded_from_automatic_onboarding(): void
    {
        $user = $this->user();
        $user->forceFill(['admin_onboarding_enabled' => false])->save();
        $this->claim($this->login($user))->assertOk()->assertJsonPath('show', false);
        $this->assertDatabaseCount('user_onboarding_visits', 0);
    }

    public function test_guest_and_disabled_users_cannot_claim_tours(): void
    {
        $this->postJson('/api/onboarding/admin-web/start')->assertUnauthorized();
        $user = $this->user();
        $token = $this->login($user);
        $user->forceFill(['is_active' => false])->save();
        $this->claim($token)->assertForbidden();
        $this->assertDatabaseHas('user_onboarding_visits', ['user_id' => $user->id, 'started_at' => null]);
    }
}
