<?php
namespace Tests\Feature;

use App\Domains\Push\Services\PushSender;
use App\Models\{Branch, Client, ClientAccount, LoanApplication, LoanParticipation, Promoter, Rate, Team, User};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Crypt, DB};
use Illuminate\Support\Str;
use Tests\TestCase;

final class ClientPushTest extends TestCase
{
    use RefreshDatabase;

    private function account(): ClientAccount
    {
        $branch = Branch::create(['name' => 'Push prueba', 'is_active' => true]);
        $client = Client::create(['branch_id' => $branch->id, 'curp' => 'PUSH'.Str::uuid(), 'first_name' => 'Prueba', 'last_name' => 'Push', 'status' => 'activo']);
        return ClientAccount::create(['client_id' => $client->id, 'password' => 'SeguraCliente123', 'is_active' => true]);
    }

    private function due(ClientAccount $a, string $due, string $paid = '0.00'): string
    {
        $user = User::factory()->create(['branch_id' => $a->client->branch_id]);
        $promoter = Promoter::create(['id' => $user->id, 'branch_id' => $user->branch_id, 'is_active' => true]);
        $team = Team::create(['branch_id' => $user->branch_id, 'promoter_id' => $promoter->id, 'name' => 'Prueba', 'created_by' => $user->id]);
        $loan = LoanApplication::create(['branch_id' => $user->branch_id, 'team_id' => $team->id, 'promoter_id' => $user->id, 'rate_id' => Rate::firstOrFail()->id, 'status' => 'entregado', 'total_requested' => 1000, 'total_approved' => 1000]);
        $part = LoanParticipation::create(['branch_id' => $user->branch_id, 'loan_application_id' => $loan->id, 'client_id' => $a->client_id, 'role_in_group' => 'integrante', 'amount_requested' => 1000, 'amount_approved' => 1000]);
        $id = (string) Str::uuid();
        DB::table('loan_installments')->insert(['id' => $id, 'branch_id' => $user->branch_id, 'loan_application_id' => $loan->id, 'loan_participation_id' => $part->id, 'client_id' => $a->client_id, 'number' => 1, 'due_on' => $due, 'amount_due' => '80.00', 'amount_paid' => $paid, 'created_at' => now()]);
        return $id;
    }

    private function device(ClientAccount $a, ?int $tokenId = null): string
    {
        $id = (string) Str::uuid();
        DB::table('client_push_devices')->insert(['id' => $id, 'client_account_id' => $a->id, 'session_token_id' => $tokenId ?? 999, 'credential_fingerprint' => hash('sha256', $a->password), 'installation_id' => (string) Str::uuid(), 'platform' => 'android', 'token_hash' => hash('sha256', $id), 'token_encrypted' => Crypt::encryptString($id), 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        return $id;
    }

    public function test_disabled_provider_never_enrolls_or_sends_and_staff_is_rejected(): void
    {
        config(['client_push.enabled' => false]);
        $a = $this->account();
        $token = $a->createToken('test', ['client:read'])->plainTextToken;
        $this->withToken($token)->getJson('/api/client/push-device')->assertOk()->assertJsonPath('available.android', false)->assertJsonPath('enabled', false);
        $this->withToken($token)->postJson('/api/client/push-device', ['platform' => 'android', 'token' => str_repeat('a', 40), 'installation_id' => (string) Str::uuid(), 'consent' => true])->assertStatus(503);
        $this->artisan('clients:payment-reminders')->assertSuccessful();
        $this->assertDatabaseCount('client_payment_reminders', 0);
        $this->assertDatabaseCount('client_push_devices', 0);
        app('auth')->forgetGuards();
        $staff = User::factory()->create();
        $this->withToken($staff->createToken('staff')->plainTextToken)->getJson('/api/client/push-device')->assertForbidden();
    }

    public function test_enrollment_encrypts_requires_consent_and_logout_revokes_only_session(): void
    {
        $sender = $this->mock(PushSender::class);
        $sender->shouldReceive('ready')->andReturn(true);
        $a = $this->account();
        $token = $a->createToken('test', ['client:read']);
        $payload = ['platform' => 'android', 'token' => str_repeat('b', 40), 'installation_id' => (string) Str::uuid(), 'consent' => false];
        $this->withToken($token->plainTextToken)->postJson('/api/client/push-device', $payload)->assertUnprocessable();
        $payload['consent'] = true;
        $this->postJson('/api/client/push-device', $payload)->assertOk();
        $saved = DB::table('client_push_devices')->first();
        $this->assertNotSame($payload['token'], $saved->token_encrypted);
        $this->assertSame($payload['token'], Crypt::decryptString($saved->token_encrypted));
        $other = $this->device($a, 999);
        $this->postJson('/api/client/logout')->assertNoContent();
        $this->assertDatabaseHas('client_push_devices', ['id' => $saved->id, 'enabled' => false]);
        $this->assertDatabaseHas('client_push_devices', ['id' => $other, 'enabled' => true]);
    }

    public function test_tomorrow_unpaid_timezone_dedup_and_password_revocation(): void
    {
        // UTC Oct30 02:00 remains Oct29 in Cancun; tomorrow is Oct30.
        $this->travelTo(CarbonImmutable::parse('2026-10-30T02:00:00Z'));
        config(['client_push.enabled' => true]);
        $a = $this->account(); $this->device($a); $this->due($a, '2026-10-30', '30.00');
        $paid = $this->account(); $this->device($paid); $this->due($paid, '2026-10-30', '80.00');
        $future = $this->account(); $this->device($future); $this->due($future, '2026-10-31');
        $changed = $this->account(); $this->device($changed); $this->due($changed, '2026-10-30'); $changed->update(['password' => 'OtraSegura123']);
        $sender = $this->mock(PushSender::class);
        $sender->shouldReceive('ready')->andReturn(true);
        $sender->shouldReceive('send')->once()->andReturn(['status' => 'accepted', 'reference' => 'provider-test']);
        $this->artisan('clients:payment-reminders')->assertSuccessful();
        $this->artisan('clients:payment-reminders')->assertSuccessful();
        $this->assertDatabaseCount('client_payment_reminders', 1);
        $this->assertDatabaseHas('client_payment_reminders', ['client_account_id' => $a->id, 'due_on' => '2026-10-30', 'status' => 'accepted']);
    }

    public function test_unknown_delivery_is_not_resent_and_disabled_account_is_skipped(): void
    {
        $this->freezeTime(); config(['client_push.enabled' => true]);
        $a = $this->account(); $this->device($a); $this->due($a, now('America/Cancun')->addDay()->toDateString());
        $b = $this->account(); $this->device($b); $this->due($b, now('America/Cancun')->addDay()->toDateString()); $b->update(['is_active' => false]);
        $sender = $this->mock(PushSender::class);
        $sender->shouldReceive('ready')->andReturn(true);
        $sender->shouldReceive('send')->once()->andThrow(new \RuntimeException('Network interrupted'));
        $this->artisan('clients:payment-reminders')->assertSuccessful();
        $this->artisan('clients:payment-reminders')->assertSuccessful();
        $this->assertDatabaseCount('client_payment_reminders', 1);
        $this->assertDatabaseHas('client_payment_reminders', ['client_account_id' => $a->id, 'status' => 'unknown']);
    }
}
