<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SessionInactivityTest extends TestCase
{
    use RefreshDatabase;

    private function clientAccount(): ClientAccount
    {
        $branch = Branch::create(['name' => 'Idle', 'is_active' => true]);
        $client = Client::create(['branch_id' => $branch->id, 'curp' => 'IDLE900101HQRXXX01', 'first_name' => 'Idle', 'last_name' => 'Demo', 'status' => 'activo']);

        return ClientAccount::create(['client_id' => $client->id, 'password' => 'ClienteSegura123', 'is_active' => true]);
    }

    private function tokenRequest(string $token): static
    {
        app('auth')->forgetGuards();

        return $this->withToken($token);
    }

    public function test_staff_token_expires_at_exactly_thirty_minutes_without_activity(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['is_active' => true]);
        $token = $user->createToken('web', ['*'], now()->addHours(8));
        $other = $user->createToken('mobile', ['*'], now()->addHours(8));
        $this->travel(29)->minutes();
        $this->tokenRequest($token->plainTextToken)->getJson('/api/me')->assertOk();
        $this->travel(1)->minutes();
        $this->tokenRequest($token->plainTextToken)->getJson('/api/me')->assertUnauthorized()->assertJsonPath('code', 'SESSION_IDLE_EXPIRED');
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $other->accessToken->id]);
    }

    public function test_only_explicit_user_activity_renews_the_session(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['is_active' => true]);
        $token = $user->createToken('staff', ['*'], now()->addHours(8));
        $this->travel(20)->minutes();
        $this->tokenRequest($token->plainTextToken)->postJson('/api/session/activity', ['idle_seconds' => 60])->assertNoContent();
        // The activity happened one minute before the heartbeat, not at request time.
        $last = DB::table('personal_access_tokens')->where('id', $token->accessToken->id)->value('last_activity_at');
        $this->assertEquals(now()->subMinute()->format('Y-m-d H:i:s'), $last);
        $this->travel(28)->minutes();
        $this->tokenRequest($token->plainTextToken)->getJson('/api/me')->assertOk();
        $this->travel(1)->minutes();
        $this->tokenRequest($token->plainTextToken)->postJson('/api/session/activity', ['idle_seconds' => 0])->assertUnauthorized();
    }

    public function test_client_tokens_also_expire_and_heartbeat_cannot_resurrect_them(): void
    {
        $this->freezeTime();
        $client = $this->clientAccount();
        $token = $client->createToken('client', ['client:read'], now()->addHours(8));
        $this->travel(25)->minutes();
        $this->tokenRequest($token->plainTextToken)->postJson('/api/session/activity', ['idle_seconds' => 0])->assertNoContent();
        $this->travel(29)->minutes();
        $this->tokenRequest($token->plainTextToken)->getJson('/api/client/me')->assertOk();
        $this->travel(1)->minutes();
        $this->tokenRequest($token->plainTextToken)->getJson('/api/client/me')->assertUnauthorized();
        $this->tokenRequest($token->plainTextToken)->postJson('/api/session/activity', ['idle_seconds' => 0])->assertUnauthorized();
    }

    public function test_heartbeat_rejects_invalid_payload_and_disabled_accounts(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $token = $user->createToken('test')->plainTextToken;
        foreach ([-1, 1800, 'invalid'] as $seconds) {
            $this->tokenRequest($token)->postJson('/api/session/activity', ['idle_seconds' => $seconds])->assertUnprocessable();
        }
        $user->forceFill(['is_active' => false])->save();
        $this->tokenRequest($token)->postJson('/api/session/activity', ['idle_seconds' => 0])->assertUnauthorized();
    }
}
