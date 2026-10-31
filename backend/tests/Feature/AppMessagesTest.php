<?php

namespace Tests\Feature;

use App\Domains\Messages\Services\MessageWriter;
use App\Domains\Push\Services\PushSender;
use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientAccount;
use App\Models\LoanApplication;
use App\Models\LoanParticipation;
use App\Models\Promoter;
use App\Models\Rate;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AppMessagesTest extends TestCase
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

    public function test_calendar_generates_before_today_thanks_and_partial_followup_once(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-30T02:00:00Z'));
        $a = $this->account();
        $this->due($a, '2026-10-30');
        $this->due($a, '2026-10-29');
        $this->due($a, '2026-10-28', '80.00');
        $this->due($a, '2026-10-28', '30.00');
        $this->due($a, '2026-10-30', '80.00');
        $this->due($a, '2026-11-01');
        $this->artisan('messages:plan-payments')->assertSuccessful();
        $this->artisan('messages:plan-payments')->assertSuccessful();
        $this->assertDatabaseCount('app_messages', 4);
        $this->assertSame('2026-10-30 04:59:59', DB::table('app_messages')->value('expires_at'));
        foreach (['before_due', 'due_today', 'after_thanks', 'after_reminder'] as $kind) {
            $this->assertDatabaseHas('app_messages', ['kind' => $kind, 'recipient_id' => $a->id]);
        }
        $this->assertStringNotContainsString('Tu pago semanal vence hoy', DB::table('app_messages')->get()->toJson());
        $this->withToken($a->createToken('app', ['client:read'])->plainTextToken)->getJson('/api/client/messages')->assertOk()->assertJsonCount(4, 'data');
        $other = $this->account();
        app('auth')->forgetGuards();
        $this->withToken($other->createToken('other', ['client:read'])->plainTextToken)->postJson('/api/client/messages/'.DB::table('app_messages')->value('id').'/read')->assertNotFound();
    }

    public function test_custom_messages_require_permission_scope_and_idempotent_payload(): void
    {
        $a = $this->account();
        $other = $this->account();
        $manager = User::factory()->create(['branch_id' => $a->client->branch_id]);
        $manager->assignRole('Gerente');
        $colleague = User::factory()->create(['branch_id' => $manager->branch_id, 'name' => 'Personal QA']);
        $this->withToken($manager->createToken('manager')->plainTextToken);
        $this->getJson('/api/messaging/recipients?type=staff&search=Personal')->assertOk()->assertJsonCount(1, 'data');
        $p = ['id' => (string) Str::uuid(), 'type' => 'client', 'recipient_id' => $other->id, 'title' => 'Aviso', 'body' => 'Consulta en sucursal.'];
        $this->postJson('/api/messaging/send', $p)->assertNotFound();
        $p['recipient_id'] = $a->id;
        $id = $this->postJson('/api/messaging/send', $p)->assertCreated()->json('id');
        $this->postJson('/api/messaging/send', $p)->assertCreated()->assertJsonPath('id', $id);
        $p['body'] = 'Otro texto';
        $this->postJson('/api/messaging/send', $p)->assertConflict();
        $this->assertDatabaseCount('app_messages', 1);
        $this->assertStringNotContainsString('Consulta en sucursal', DB::table('access_audits')->get()->toJson());
        $p['id'] = (string) Str::uuid();
        $p['type'] = 'staff';
        $p['recipient_id'] = $colleague->id;
        $this->postJson('/api/messaging/send', $p)->assertCreated();
        app('auth')->forgetGuards();
        $this->withToken($colleague->createToken('staff')->plainTextToken);
        $this->getJson('/api/messages')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.body', 'Otro texto');
        $this->postJson('/api/messaging/send', $p)->assertForbidden();
        app('auth')->forgetGuards();
        $this->withToken($a->createToken('client', ['client:read'])->plainTextToken);
        $this->getJson('/api/client/messages')->assertOk()->assertJsonPath('data.0.body', 'Consulta en sucursal.');
        $this->postJson('/api/client/messages/'.$id.'/read')->assertNoContent();
        $this->getJson('/api/client/messages')->assertJsonPath('unread', 0);
        $this->postJson('/api/messaging/send', $p)->assertForbidden();
    }

    public function test_dispatch_deduplicates_checks_current_balance_and_sends_staff_with_own_topic(): void
    {
        config(['client_push.enabled' => true]);
        $a = $this->account();
        $this->device($a);
        $installment = $this->due($a, now('America/Cancun')->addDay()->toDateString());
        $this->artisan('messages:plan-payments')->assertSuccessful();
        $sender = $this->mock(PushSender::class);
        $sender->shouldReceive('ready')->andReturn(true);
        $sender->shouldReceive('sendMessage')->once()->withArgs(fn ($platform, $token, $dedup, $title, $body, $audience) => $audience === 'client' && $title === 'Tu pago vence mañana')->andReturn(['status' => 'accepted', 'reference' => 'fake']);
        $this->artisan('messages:dispatch')->assertSuccessful();
        $this->artisan('messages:dispatch')->assertSuccessful();
        $this->assertDatabaseCount('app_message_deliveries', 1);
        DB::table('loan_installments')->where('id', $installment)->update(['amount_paid' => '80.00']);
        $this->artisan('messages:dispatch')->assertSuccessful();
        $this->assertNotNull(DB::table('app_messages')->value('cancelled_at'));
        $staff = User::factory()->create(['branch_id' => $a->client->branch_id]);
        $this->app->forgetInstance(PushSender::class);
        $sender = $this->mock(PushSender::class);
        $sender->shouldReceive('ready')->andReturn(true);
        $sender->shouldReceive('sendMessage')->once()->withArgs(fn ($platform, $token, $dedup, $title, $body, $audience) => $audience === 'staff' && ! str_contains($body, 'Privado'))->andReturn(['status' => 'accepted', 'reference' => 'fake-staff']);
        $this->withToken($staff->createToken('app')->plainTextToken)->postJson('/api/push-device', ['installation_id' => (string) Str::uuid(), 'platform' => 'ios', 'token' => str_repeat('a', 64), 'consent' => true])->assertOk();
        app(MessageWriter::class)->create('staff', $staff->id, $staff->branch_id, 'test', 'custom', 'Privado', 'Privado cuerpo');
        $this->artisan('messages:dispatch')->assertSuccessful();
        $this->artisan('messages:dispatch')->assertSuccessful();
        $this->assertDatabaseHas('app_message_deliveries', ['provider_reference' => 'fake-staff']);
        $this->postJson('/api/logout')->assertNoContent();
        $this->assertDatabaseHas('staff_push_devices', ['user_id' => $staff->id, 'enabled' => false]);
    }

    public function test_fcm_configuration_check_is_validation_only_and_does_not_expose_credentials(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'crece-fcm-qa-');
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        file_put_contents($path, json_encode(['type' => 'service_account', 'project_id' => 'crece-qa', 'client_email' => 'test@crece-qa.iam.gserviceaccount.com', 'private_key' => $private]));
        chmod($path, 0600);
        config(['client_push.fcm_credentials' => $path, 'client_push.enabled' => false]);
        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-only-access-token']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/crece-qa/messages/validation']),
        ]);
        try {
            $this->artisan('messages:check-push --verify-fcm')->assertSuccessful();
            Http::assertSent(fn ($r) => str_contains($r->url(), 'fcm.googleapis.com') && $r['validate_only'] === true && $r['message']['topic'] === 'crece-configuration-check');
            $this->assertDatabaseCount('app_messages', 0);
            $this->assertDatabaseCount('app_message_deliveries', 0);
        } finally {
            unlink($path);
        }
    }
}
