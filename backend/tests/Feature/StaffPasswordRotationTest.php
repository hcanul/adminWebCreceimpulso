<?php

namespace Tests\Feature;

use App\Actions\Auth\ChangeStaffPasswordAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class StaffPasswordRotationTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $u = User::factory()->create(['is_active' => true, 'password' => 'OriginalClave123']);
        $u->assignRole('Super Admin');
        return $u;
    }

    public function test_expiry_boundary_and_existing_accounts_without_date(): void
    {
        $u = $this->user();
        $u->password_changed_at = now()->subDays(15)->addSecond();
        $u->save();
        $this->assertFalse($u->password_change_required);
        $this->travel(1)->seconds();
        $this->assertTrue($u->password_change_required);
        $u->password_changed_at = null;
        $u->save();
        $this->assertTrue($u->password_change_required);
    }

    public function test_expired_staff_can_only_read_profile_logout_or_change_password(): void
    {
        $u = $this->user();
        $u->forceFill(['password_changed_at' => now()->subDays(15)])->save();
        $result = $this->postJson('/api/login', ['email' => $u->email, 'password' => 'OriginalClave123', 'device_name' => 'test'])->assertOk()->assertJsonPath('data.user.password_change_required', true);
        $this->withToken($result->json('data.token'));
        $this->getJson('/api/me')->assertOk()->assertJsonPath('password_change_required', true)->assertJsonMissingPath('previous_password');
        $this->getJson('/api/administration/options')->assertForbidden()->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
        $this->postJson('/api/intake/teams', [])->assertForbidden()->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
        $this->postJson('/api/password', ['current_password' => 'incorrect', 'password' => 'RenovadaClave456', 'password_confirmation' => 'RenovadaClave456'])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->postJson('/api/password', ['current_password' => 'OriginalClave123', 'password' => 'OriginalClave123', 'password_confirmation' => 'OriginalClave123'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $changed = $this->postJson('/api/password', ['current_password' => 'OriginalClave123', 'password' => 'RenovadaClave456', 'password_confirmation' => 'RenovadaClave456'])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/me')->assertUnauthorized();
        $this->withToken($changed->json('token'));
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/me')->assertOk()->assertJsonPath('password_change_required', false);
        $this->getJson('/api/administration/options')->assertOk();
        $this->assertTrue(Hash::check('RenovadaClave456', $u->fresh()->password));
        $this->assertSame(1, $u->tokens()->count());
        $this->assertStringNotContainsString('RenovadaClave456', DB::table('access_audits')->where('action', 'password_changed')->first()->changes);
    }

    public function test_previous_password_cannot_be_reused_and_all_other_sessions_are_revoked(): void
    {
        $u = $this->user();
        $u->createToken('other device');
        app(ChangeStaffPasswordAction::class)->execute($u, 'OriginalClave123', 'RenovadaClave456');
        $this->assertSame(1, $u->tokens()->count());
        try {
            app(ChangeStaffPasswordAction::class)->execute($u->fresh(), 'RenovadaClave456', 'OriginalClave123');
            $this->fail('Previous password must be refused');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('password', $e->errors());
        }
        $u->refresh();
        $this->assertTrue(Hash::check('RenovadaClave456', $u->password));
        $this->assertArrayNotHasKey('previous_password', $u->toArray());
    }
}
