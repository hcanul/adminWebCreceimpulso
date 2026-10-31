<?php

namespace Tests\Feature;

use App\Domains\Credit\Services\CreditCalculator;
use App\Domains\Credit\Services\CreditDisbursement;
use App\Domains\Credit\Services\CreditReview;
use App\Domains\Push\Services\PushSender;
use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientAccount;
use App\Models\ClientApplication;
use App\Models\LoanApplication;
use App\Models\LoanParticipation;
use App\Models\Promoter;
use App\Models\Rate;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class CreditWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Existing workflow tests cover pre-policy credits; dedicated tests enable new authorizations.
        config(['late_fees.new_authorizations' => false]);
    }

    public function test_client_statement_is_personal_and_group_aggregate_with_scoped_access(): void
    {
        $this->freezeTime();
        $f = $this->collectionFixture('cash_branch');
        $this->postJson($f['base'].'/payments', $f['payment'])->assertOk();
        $account = ClientAccount::create(['client_id' => $f['clients'][0]->id, 'password' => 'TestPassword123', 'is_active' => true]);
        Sanctum::actingAs($account, ['client:read']);
        $data = $this->getJson('/api/client/statement')->assertOk()->assertJsonPath('active', true)->assertJsonCount(16, 'personal')->assertJsonCount(16, 'group_schedule')->assertJsonPath('personal.0.status', 'partial')->assertJsonPath('personal.0.paid', 50000)->assertJsonMissingPath('members')->json();
        $this->assertSame(1152000, array_sum(array_column($data['personal'], 'amount')));
        $this->assertSame(2816000, array_sum(array_column($data['group_schedule'], 'amount')));
        $this->assertArrayNotHasKey('client_id', $data['group_schedule'][0]);
        $this->travelTo(CarbonImmutable::parse($f['delivery']['first_due_on'], 'America/Cancun')->addDay());
        $this->getJson('/api/client/statement')->assertOk()->assertJsonPath('personal.0.status', 'overdue')->assertJsonPath('personal.1.status', 'upcoming');
        $other = Client::create(['branch_id' => $f['branch']->id, 'curp' => 'OTHER'.Str::uuid(), 'first_name' => 'Otro', 'last_name' => 'Cliente', 'status' => 'activo']);
        Sanctum::actingAs(ClientAccount::create(['client_id' => $other->id, 'password' => 'TestPassword123', 'is_active' => true]), ['client:read']);
        $this->getJson('/api/client/statement?loan_id='.$f['loan']['id'])->assertOk()->assertExactJson(['active' => false]);
        Sanctum::actingAs($f['cashier']);
        $this->getJson('/api/client/statement')->assertForbidden();
        Sanctum::actingAs($account, []);
        $this->getJson('/api/client/statement')->assertForbidden();
    }

    public function test_payment_confirmation_is_transactional_and_retry_does_not_duplicate(): void
    {
        $f=$this->collectionFixture('cash_branch');
        $a=\App\Models\ClientAccount::create(['client_id'=>$f['clients'][0]->id,'password'=>'SecretPassword123','is_active'=>true]);
        $bad=$f['payment'];$bad['amount']='900.00';
        $this->postJson($f['base'].'/payments',$bad)->assertUnprocessable();$this->assertDatabaseCount('app_messages',0);
        $this->postJson($f['base'].'/payments',$f['payment'])->assertOk();
        $this->postJson($f['base'].'/payments',$f['payment'])->assertOk();
        $this->assertDatabaseCount('app_messages',1);$this->assertDatabaseHas('app_messages',['recipient_id'=>$a->id,'kind'=>'payment_received','payment_id'=>$f['payment']['id']]);
        $reversal = $this->reversalInput($f);
        Sanctum::actingAs($f['manager']);
        $this->postJson('/api/credit/receipts/'.$f['payment']['id'].'/reverse', $reversal)->assertOk();
        $this->assertNotNull(DB::table('app_messages')->value('cancelled_at'));
        $this->assertDatabaseCount('app_messages', 1);
        Sanctum::actingAs($a, ['client:read']);
        $this->getJson('/api/client/messages')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_client_receipts_hide_other_members_and_remain_verifiable_after_reversal(): void
    {
        $f = $this->collectionFixture('bank_deposit');
        $this->postJson($f['base'].'/payments', $f['payment'])->assertOk();
        $reversal = $this->reversalInput($f);
        $accounts = [];
        foreach ($f['clients'] as $client) {
            $accounts[] = ClientAccount::create(['client_id' => $client->id, 'password' => 'TestPassword123', 'is_active' => true]);
        }
        Sanctum::actingAs($accounts[0], ['client:read']);
        $receipt = $this->getJson('/api/client/payments')->assertOk()->assertJsonCount(1, 'receipts')
            ->assertJsonPath('has_more', false)->assertJsonPath('receipts.0.personal_applied', '500.00')
            ->assertJsonPath('receipts.0.status', 'registered')->assertJsonMissingPath('receipts.0.allocations')
            ->assertJsonMissingPath('receipts.0.bank_reference')->assertJsonMissingPath('receipts.0.actor_name')->json('receipts.0');
        $this->get($receipt['verification_url'])->assertOk()->assertSee('Pago registrado');
        $this->get($receipt['verification_url'].'&tampered=1')->assertNotFound();
        $this->getJson('/api/client/statement')->assertOk()->assertJsonPath('terms.requested', 1000000)
            ->assertJsonPath('terms.approved', 900000)->assertJsonPath('terms.guarantee_retained', 90000)
            ->assertJsonPath('late_fees.balance', 0);
        Sanctum::actingAs($accounts[1], ['client:read']);
        $this->getJson('/api/client/payments?client_id='.$f['clients'][0]->id)->assertOk()->assertJsonCount(0, 'receipts');
        Sanctum::actingAs($f['manager']);
        $this->getJson('/api/client/payments')->assertForbidden();
        $this->postJson('/api/credit/receipts/'.$f['payment']['id'].'/reverse', $reversal)->assertOk();
        Sanctum::actingAs($accounts[0], ['client:read']);
        $this->getJson('/api/client/payments')->assertOk()->assertJsonPath('receipts.0.status', 'reversed');
        $this->get($receipt['verification_url'])->assertOk()->assertSee('Pago revertido');
        $this->getJson('/api/client/statement')->assertOk()->assertJsonPath('personal.0.paid', 0);
        $this->getJson('/api/client/payments?page=0')->assertUnprocessable();
        Sanctum::actingAs($accounts[0], []);
        $this->getJson('/api/client/payments')->assertForbidden();
    }

    public function test_push_reminder_only_for_tomorrow_unpaid_and_not_duplicated(): void
    {
        $f = $this->collectionFixture('cash_branch');
        $account = ClientAccount::create(['client_id' => $f['clients'][0]->id, 'password' => 'TestPassword123', 'is_active' => true]);
        $token = $account->createToken('device', ['client:read'])->plainTextToken;
        $installation = (string) Str::uuid();
        $sender = new class extends PushSender
        {
            public int $calls = 0;

            public function ready(string $platform): bool
            {
                return (bool) config('client_push.enabled');
            }

            public function send(string $platform, string $token, string $dedup): array
            {
                $this->calls++;

                return ['status' => 'accepted', 'reference' => 'test'];
            }
        };
        $this->app->instance(PushSender::class, $sender);
        $this->app['auth']->forgetGuards();
        $this->withToken($token);
        $input = ['installation_id' => $installation, 'platform' => 'android', 'token' => str_repeat('a', 80), 'consent' => true];
        config(['client_push.enabled' => false]);
        $this->postJson('/api/client/push-device', $input)->assertStatus(503);
        $this->artisan('clients:payment-reminders')->assertSuccessful();
        $this->assertSame(0, $sender->calls);
        config(['client_push.enabled' => true]);
        $this->postJson('/api/client/push-device', $input)->assertOk();
        $this->assertStringNotContainsString($input['token'], DB::table('client_push_devices')->get()->toJson());
        $this->artisan('clients:payment-reminders')->assertSuccessful();
        $this->assertSame(0, $sender->calls);
        $this->travelTo(CarbonImmutable::parse($f['delivery']['first_due_on'], 'America/Cancun')->subDay()->setTime(9, 0));
        $this->artisan('clients:payment-reminders')->assertSuccessful();
        $this->artisan('clients:payment-reminders')->assertSuccessful();
        $this->assertSame(1, $sender->calls);
        $this->assertDatabaseCount('client_payment_reminders', 1);
        $this->assertDatabaseHas('client_payment_reminders', ['status' => 'accepted']);
        $this->travel(7)->days();
        $account->update(['password' => 'ChangedPassword456']);
        $this->artisan('clients:payment-reminders')->assertSuccessful();
        $this->assertSame(1, $sender->calls);
    }

    public function test_push_skips_paid_installments_and_logout_revokes_registration(): void
    {
        $f = $this->collectionFixture('cash_branch');
        $account = ClientAccount::create(['client_id' => $f['clients'][0]->id, 'password' => 'TestPassword123', 'is_active' => true]);
        $token = $account->createToken('device', ['client:read'])->plainTextToken;
        $sender = new class extends PushSender
        {
            public int $calls = 0;

            public function ready(string $platform): bool
            {
                return true;
            }

            public function send(string $platform, string $token, string $dedup): array
            {
                $this->calls++;

                return ['status' => 'accepted', 'reference' => 'test'];
            }
        };
        $this->app->instance(PushSender::class, $sender);
        config(['client_push.enabled' => true]);
        $this->app['auth']->forgetGuards();
        $this->withToken($token);
        $input = ['installation_id' => (string) Str::uuid(), 'platform' => 'ios', 'token' => str_repeat('a', 64), 'consent' => true];
        $this->postJson('/api/client/push-device', $input)->assertOk();
        DB::table('loan_installments')->where('client_id', $account->client_id)->update(['amount_paid' => DB::raw('amount_due')]);
        $this->travelTo(CarbonImmutable::parse($f['delivery']['first_due_on'], 'America/Cancun')->subDay()->setTime(9, 0));
        $this->artisan('clients:payment-reminders')->assertSuccessful();
        $this->assertSame(0, $sender->calls);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/client/logout')->assertNoContent();
        $this->assertDatabaseHas('client_push_devices', ['client_account_id' => $account->id, 'enabled' => false]);
    }

    private function renewStaffFixturePasswords(array $fixture): void
    {
        // Long financial scenarios assume staff renewed their access during the elapsed weeks.
        // Rotation itself is covered by StaffPasswordRotationTest; middleware remains enabled here.
        foreach ($fixture as $value) {
            if ($value instanceof User) {
                $value->forceFill(['password_changed_at' => now()])->save();
            }
        }
    }

    private function canonicalObject(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($item) => is_array($item) ? $this->canonicalObject($item) : $item, $value);
    }

    private function failWrite(string $table, string $operation): void
    {
        // Inject a failure without database-specific triggers or implicit DDL commits.
        DB::connection()->beforeExecuting(function (string $sql) use ($table, $operation) {
            $normalized = strtolower(str_replace(['`', '"'], '', $sql));
            $prefix = $operation === 'insert' ? 'insert into ' : 'update ';
            if (str_starts_with($normalized, $prefix.$table.' ')) {
                throw new \RuntimeException('Simulated persistence failure.');
            }
        });
    }

    private function fixture(): array
    {
        $branch = Branch::create(['name' => 'Carrillo', 'is_active' => true]);
        $manager = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $manager->assignRole('Gerente');
        $user = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $user->assignRole('Promotor');
        $promoter = Promoter::create(['id' => $user->id, 'branch_id' => $branch->id, 'is_active' => true]);
        $team = Team::create(['name' => 'Las Flores', 'branch_id' => $branch->id, 'promoter_id' => $promoter->id, 'created_by' => $manager->id]);
        $clients = [];
        foreach (['presidenta', 'tesorera', 'integrante'] as $i => $role) {
            $client = Client::create(['branch_id' => $branch->id, 'curp' => 'TEST'.Str::uuid(), 'first_name' => 'Integrante '.$i, 'last_name' => 'Prueba', 'status' => 'activo']);
            TeamMembership::create(['branch_id' => $branch->id, 'team_id' => $team->id, 'client_id' => $client->id, 'role_in_group' => $role]);
            ClientApplication::create(['branch_id' => $branch->id, 'team_id' => $team->id, 'client_id' => $client->id, 'created_by' => $manager->id, 'status' => 'en_revision', 'fields' => [], 'submitted_at' => now()]);
            $clients[] = $client;
        }
        $rate = Rate::where('name', 'Cobre')->firstOrFail();
        $payload = ['id' => (string) Str::uuid(), 'team_id' => $team->id, 'rate_id' => $rate->id, 'rate_signature' => $rate->terms_hash, 'member_limit' => '12000', 'participants' => array_map(fn ($client, $amount) => ['client_id' => $client->id, 'amount_requested' => $amount], $clients, ['10000', '8000', '5000'])];
        Sanctum::actingAs($manager);

        return compact('manager', 'user', 'branch', 'team', 'clients', 'rate', 'payload');
    }

    public function test_creates_immutable_group_request_with_distinct_amounts_and_common_rate(): void
    {
        $f = $this->fixture();
        $this->getJson('/api/credit/teams/'.$f['team']->id.'/context')->assertOk()->assertJsonCount(0, 'issues')->assertJsonPath('members.0.eligible', true);
        $saved = $this->postJson('/api/credit/applications', $f['payload'])->assertCreated()->assertJsonPath('total_requested', '23000.00')->assertJsonPath('total_approved', null)->assertJsonPath('status', 'pendiente')->assertJsonPath('disbursement_method', null)->assertJsonPath('repayment_method', null)->assertJsonPath('request_snapshot.totals.weekly_payment', '1840.00')->assertJsonPath('request_snapshot.totals.total_repayment', '29440.00')->assertJsonPath('request_snapshot.totals.interest', '6440.00')->assertJsonPath('request_snapshot.totals.guarantee', '2300.00')->assertJsonPath('request_snapshot.totals.net_delivery', '20700.00')->assertJsonPath('request_snapshot.weeks', 16)->json();
        $this->assertStringStartsWith('CRE-', $saved['folio']);
        $this->assertDatabaseCount('loan_participations', 3);
        $this->assertDatabaseHas('loan_participations', ['loan_application_id' => $saved['id'], 'branch_id' => $f['branch']->id, 'client_id' => $f['clients'][0]->id, 'amount_requested' => 10000, 'amount_approved' => null]);
        $f['rate']->update(['rate_factor' => '70']);
        $this->getJson('/api/credit/applications/'.$saved['id'])->assertOk()->assertJsonPath('request_snapshot.rate.factor', '80.0000');
        $this->expectException(\LogicException::class);
        LoanApplication::findOrFail($saved['id'])->update(['total_requested' => 1]);
    }

    public function test_retry_is_idempotent_but_modified_payload_and_second_open_request_are_rejected(): void
    {
        $f = $this->fixture();
        $payload = $f['payload'];
        $saved = $this->postJson('/api/credit/applications', $payload)->assertCreated()->json();
        $payload['participants'] = array_reverse($payload['participants']);
        $this->postJson('/api/credit/applications', $payload)->assertCreated()->assertJsonPath('folio', $saved['folio']);
        $this->assertDatabaseCount('loan_applications', 1);
        $payload['member_limit'] = '13000';
        $this->postJson('/api/credit/applications', $payload)->assertConflict();
        $payload['id'] = (string) Str::uuid();
        $this->postJson('/api/credit/applications', $payload)->assertUnprocessable();
    }

    public function test_enforces_minimum_committee_and_ready_files(): void
    {
        $f = $this->fixture();
        $payload = $f['payload'];
        $payload['participants'] = array_slice($payload['participants'], 0, 2);
        $this->postJson('/api/credit/applications', $payload)->assertUnprocessable();
        TeamMembership::where('client_id', $f['clients'][1]->id)->update(['role_in_group' => 'integrante']);
        $this->postJson('/api/credit/applications', $f['payload'])->assertUnprocessable()->assertJsonValidationErrors('participants');
        TeamMembership::where('client_id', $f['clients'][1]->id)->update(['role_in_group' => 'tesorera']);
        ClientApplication::where('client_id', $f['clients'][1]->id)->update(['status' => 'borrador']);
        $this->postJson('/api/credit/applications', $f['payload'])->assertUnprocessable();
        $this->assertDatabaseCount('loan_applications', 0);
    }

    public function test_member_limit_precision_catalog_limits_and_changed_rate_are_checked(): void
    {
        $f = $this->fixture();
        $p = $f['payload'];
        $p['member_limit'] = '9000';
        $this->postJson('/api/credit/applications', $p)->assertUnprocessable();
        $p = $f['payload'];
        $p['participants'][0]['amount_requested'] = '100.001';
        $this->postJson('/api/credit/applications', $p)->assertUnprocessable();
        $p = $f['payload'];
        $p['total_approved'] = '23000';
        $this->postJson('/api/credit/applications', $p)->assertUnprocessable();
        $p = $f['payload'];
        $p['disbursement_method'] = 'cash_branch';
        $this->postJson('/api/credit/applications', $p)->assertUnprocessable();
        $f['rate']->update(['min_amount' => '6000']);
        $this->postJson('/api/credit/applications', $f['payload'])->assertConflict();
        $p = $f['payload'];
        $p['rate_signature'] = $f['rate']->terms_hash;
        $this->postJson('/api/credit/applications', $p)->assertUnprocessable();
    }

    public function test_mora_and_another_group_credit_block_a_member(): void
    {
        $f = $this->fixture();
        $f['clients'][0]->update(['status' => 'moroso']);
        $this->postJson('/api/credit/applications', $f['payload'])->assertUnprocessable();
        $f['clients'][0]->update(['status' => 'activo']);
        $other = Team::create(['branch_id' => $f['branch']->id, 'name' => 'Otro', 'promoter_id' => $f['user']->id]);
        $loan = LoanApplication::create(['branch_id' => $f['branch']->id, 'team_id' => $other->id, 'promoter_id' => $f['user']->id, 'rate_id' => $f['rate']->id, 'status' => 'entregado']);
        LoanParticipation::create(['loan_application_id' => $loan->id, 'client_id' => $f['clients'][0]->id, 'amount_requested' => 1000]);
        $this->postJson('/api/credit/applications', $f['payload'])->assertUnprocessable();
    }

    public function test_scope_and_permissions_cannot_be_bypassed(): void
    {
        $f = $this->fixture();
        Sanctum::actingAs($f['user']);
        $this->getJson('/api/credit/teams/'.$f['team']->id.'/context')->assertOk();
        $this->postJson('/api/credit/applications', $f['payload'])->assertForbidden();
        $this->postJson('/api/credit/rates', [])->assertForbidden();
        $otherBranch = Branch::create(['name' => 'Tulum']);
        $outsider = User::factory()->create(['branch_id' => $otherBranch->id, 'is_active' => true]);
        $outsider->assignRole('Gerente');
        Sanctum::actingAs($outsider);
        $this->postJson('/api/credit/applications', $f['payload'])->assertNotFound();
        $this->getJson('/api/credit/teams/'.$f['team']->id.'/context')->assertNotFound();
        Sanctum::actingAs($f['manager']);
        $saved = $this->postJson('/api/credit/applications', $f['payload'])->assertCreated()->json();
        Sanctum::actingAs($outsider);
        $this->getJson('/api/credit/applications/'.$saved['id'])->assertNotFound();
    }

    public function test_rates_can_be_configured_by_general_manager_and_bad_factors_are_rejected(): void
    {
        $f = $this->fixture();
        $f['manager']->syncRoles(['Gerente General']);
        $p = ['name' => 'Nueva', 'cicle' => 1, 'rate_factor' => '75', 'min_amount' => null, 'max_amount' => null, 'is_active' => true];
        $this->postJson('/api/credit/rates', $p)->assertCreated()->assertJsonPath('calculation_method', 'weekly_per_thousand');
        $p['rate_factor'] = '0.15';
        $this->postJson('/api/credit/rates', $p)->assertUnprocessable();
    }

    public function test_exact_standard_calculations_and_rounding(): void
    {
        $calculator = new CreditCalculator;
        foreach (['80' => ['800.00', '12800.00', '2800.00'], '75' => ['750.00', '12000.00', '2000.00'], '70' => ['700.00', '11200.00', '1200.00']] as $factor => $expected) {
            $result = $calculator->estimate(1000000, (string) $factor);
            $this->assertSame($expected, [$result['weekly_payment'], $result['total_repayment'], $result['interest']]);
            $this->assertSame('1000.00', $result['guarantee']);
            $this->assertSame('9000.00', $result['net_delivery']);
        }
        $this->assertSame('98.76', $calculator->estimate(123456, '80')['weekly_payment']);
        $this->assertSame('98.77', $calculator->estimate(123457, '80')['weekly_payment']);
    }

    private function reviewFixture(): array
    {
        $f = $this->fixture();
        $loan = $this->postJson('/api/credit/applications', $f['payload'])->assertCreated()->json();
        $reviewer = User::factory()->create(['branch_id' => $f['branch']->id, 'is_active' => true]);
        $reviewer->assignRole('Gestión');
        $checks = [];
        foreach ($f['clients'] as $client) {
            foreach (CreditReview::CHECKS as $key) {
                $checks[] = ['client_id' => $client->id, 'key' => $key, 'status' => 'verified', 'notes' => 'Cotejado con el expediente físico y registro de visita.'];
            }
        }
        Sanctum::actingAs($reviewer);

        return $f + compact('loan', 'reviewer', 'checks');
    }

    public function test_management_review_requires_every_check_and_preserves_financial_terms(): void
    {
        $f = $this->reviewFixture();
        $url = '/api/credit/applications/'.$f['loan']['id'].'/review';
        $this->getJson('/api/credit/applications?review_status=pending')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($url)->assertOk()->assertJsonPath('version', 0)->assertJsonCount(3, 'files');
        $p = ['version' => 0, 'action' => 'complete', 'notes' => 'Expedientes verificados.', 'checks' => $f['checks']];
        $p['checks'][0]['status'] = 'pending';
        $this->postJson($url, $p)->assertUnprocessable();
        $p['checks'] = $f['checks'];
        $this->postJson($url, $p)->assertOk()->assertJsonPath('status', 'ready_for_authorization')->assertJsonPath('version', 1);
        $this->postJson($url, $p)->assertConflict();
        $p['version'] = 1;
        $this->postJson($url, $p)->assertConflict();
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'history')->assertJsonPath('history.0.actor_id', $f['reviewer']->id)->assertJsonCount(3, 'history.0.files');
        $loan = LoanApplication::findOrFail($f['loan']['id']);
        $this->assertSame($this->canonicalObject($f['loan']['request_snapshot']), $this->canonicalObject($loan->request_snapshot));
        $this->assertSame('23000.00', $loan->total_requested);
        $this->assertNull($loan->total_approved);
        $this->assertSame('pendiente', $loan->status);
        $this->getJson('/api/credit/applications?review_status=pending')->assertJsonCount(0, 'data');
    }

    public function test_returns_only_observed_files_and_resubmission_invalidates_previous_checks(): void
    {
        $f = $this->reviewFixture();
        $url = '/api/credit/applications/'.$f['loan']['id'].'/review';
        $original = ClientApplication::where('client_id', $f['clients'][0]->id)->firstOrFail();
        $f['checks'][0]['status'] = 'observed';
        $f['checks'][0]['notes'] = 'Corregir documento de identidad.';
        $p = ['version' => 0, 'action' => 'return', 'notes' => 'Corregir el expediente señalado.', 'checks' => $f['checks']];
        $this->postJson($url, $p)->assertOk()->assertJsonPath('status', 'returned');
        $this->assertDatabaseHas('client_applications', ['id' => $original->id, 'status' => 'borrador', 'version' => $original->version + 1]);
        $this->assertSame(2, ClientApplication::where('status', 'en_revision')->count());
        $this->assertDatabaseHas('application_revisions', ['application_id' => $original->id, 'actor_id' => $f['reviewer']->id]);
        Sanctum::actingAs($f['manager']);
        $resubmit = ['version' => 1, 'action' => 'resubmit', 'notes' => 'Identidad corregida.', 'checks' => []];
        $this->postJson($url, $resubmit)->assertUnprocessable();
        // Simulate the existing dossier submission; its own validation is covered in IntakeWorkflowTest.
        $original->refresh()->update(['status' => 'en_revision', 'version' => $original->version + 1, 'fields' => ['pNombre' => 'Nombre corregido'], 'submitted_at' => now()]);
        $this->postJson($url, $resubmit)->assertOk()->assertJsonPath('status', 'pending')->assertJsonPath('version', 2);
        $history = $this->getJson($url)->assertOk()->assertJsonCount(0, 'checks')->assertJsonCount(2, 'history')->json('history');
        $old = collect($history[1]['files'])->firstWhere('client_id', $original->client_id);
        $this->assertSame([], $old['fields']);
        $new = collect($history[0]['files'])->firstWhere('client_id', $original->client_id);
        $this->assertSame('Nombre corregido', $new['fields']['pNombre']);
        $this->postJson($url, $resubmit)->assertConflict();
    }

    public function test_management_permissions_and_branch_scope_protect_sensitive_files(): void
    {
        $f = $this->reviewFixture();
        $url = '/api/credit/applications/'.$f['loan']['id'].'/review';
        $p = ['version' => 0, 'action' => 'save', 'checks' => $f['checks']];
        Sanctum::actingAs($f['manager']);
        $this->getJson($url)->assertOk();
        $this->postJson($url, $p)->assertForbidden();
        Sanctum::actingAs($f['user']);
        $this->getJson('/api/credit/applications/'.$f['loan']['id'])->assertOk()->assertJsonMissingPath('review_checks');
        $this->getJson($url)->assertForbidden();
        $this->postJson($url, $p)->assertForbidden();
        $branch = Branch::create(['name' => 'Otra sucursal', 'is_active' => true]);
        $f['reviewer']->forceFill(['branch_id' => $branch->id])->save();
        Sanctum::actingAs($f['reviewer']);
        $this->getJson($url)->assertNotFound();
        $this->postJson($url, $p)->assertNotFound();
        $this->getJson('/api/credit/applications?review_status=pending')->assertJsonCount(0, 'data');
        $f['reviewer']->forceFill(['branch_id' => null])->save();
        $this->getJson($url)->assertNotFound();
        $f['reviewer']->forceFill(['is_active' => false])->save();
        $this->getJson($url)->assertForbidden();
    }

    public function test_review_rejects_duplicate_foreign_checks_missing_evidence_and_stale_versions(): void
    {
        $f = $this->reviewFixture();
        $url = '/api/credit/applications/'.$f['loan']['id'].'/review';
        $p = ['version' => 0, 'action' => 'save', 'checks' => $f['checks']];
        $p['checks'][0]['notes'] = ' ';
        $this->postJson($url, $p)->assertUnprocessable();
        $p['checks'] = $f['checks'];
        $p['checks'][0] = $p['checks'][1];
        $this->postJson($url, $p)->assertUnprocessable();
        $p['checks'] = $f['checks'];
        $p['checks'][0]['client_id'] = (string) Str::uuid();
        $this->postJson($url, $p)->assertUnprocessable();
        $p['checks'] = $f['checks'];
        $p['action'] = 'return';
        $p['notes'] = 'Sin observaciones marcadas.';
        $this->postJson($url, $p)->assertUnprocessable();
        $p['action'] = 'save';
        $this->postJson($url, $p)->assertOk()->assertJsonPath('version', 1);
        $this->postJson($url, $p)->assertConflict();
        $this->assertDatabaseCount('credit_review_events', 1);
    }

    public function test_review_cannot_process_historical_or_already_approved_loans(): void
    {
        $f = $this->reviewFixture();
        $loan = LoanApplication::findOrFail($f['loan']['id']);
        $loan->update(['status' => 'aprobado']);
        $this->postJson('/api/credit/applications/'.$loan->id.'/review', ['version' => 0, 'action' => 'save', 'checks' => $f['checks']])->assertConflict();
        $this->assertDatabaseCount('credit_review_events', 0);
    }

    private function authorizationFixture(): array
    {
        $f = $this->reviewFixture();
        $this->postJson('/api/credit/applications/'.$f['loan']['id'].'/review', ['version' => 0, 'action' => 'complete', 'notes' => 'Expedientes cotejados.', 'checks' => $f['checks']])->assertOk();
        $commercial = User::factory()->create(['branch_id' => $f['branch']->id, 'is_active' => true]);
        $commercial->assignRole('Coordinación Comercial');
        $proposal = ['version' => 0, 'notes' => 'Condiciones propuestas por Gerencia.', 'disbursement_method' => 'cash_branch', 'repayment_method' => 'bank_deposit', 'participants' => array_map(fn ($client, $amount) => ['client_id' => $client->id, 'amount_proposed' => $amount], $f['clients'], ['9000', '8000', '5000'])];
        Sanctum::actingAs($f['manager']);

        return $f + compact('commercial', 'proposal');
    }

    public function test_commercial_authorization_preserves_request_and_freezes_calculated_terms_without_disbursing(): void
    {
        $f = $this->authorizationFixture();
        $base = '/api/credit/applications/'.$f['loan']['id'];
        $this->postJson($base.'/proposal', $f['proposal'])->assertOk()->assertJsonPath('status', 'pending_authorization');
        $this->assertDatabaseHas('loan_applications', ['id' => $f['loan']['id'], 'total_approved' => null, 'status' => 'pendiente', 'disbursement_method' => null]);
        $f['rate']->update(['rate_factor' => '70']);
        Sanctum::actingAs($f['commercial']);
        $this->getJson($base.'/review')->assertOk();
        $this->getJson('/api/credit/applications?authorization_status=pending_authorization')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson($base.'/authorization', ['version' => 1, 'decision' => 'approve', 'first_due_on' => now('America/Cancun')->addDays(7)->toDateString(), 'notes' => 'Autorizado conforme a las condiciones del gerente.'])->assertOk()->assertJsonPath('status', 'authorized')->assertJsonPath('version', 2);
        $this->getJson($base)->assertOk()->assertJsonPath('status', 'aprobado')->assertJsonPath('total_approved', '22000.00')->assertJsonPath('total_requested', '23000.00')->assertJsonPath('disbursement_method', 'cash_branch')->assertJsonPath('repayment_method', 'bank_deposit')->assertJsonPath('approved_snapshot.totals.weekly_payment', '1760.00')->assertJsonPath('approved_snapshot.totals.total_repayment', '28160.00')->assertJsonPath('approved_snapshot.totals.interest', '6160.00')->assertJsonPath('approved_snapshot.totals.guarantee', '2200.00')->assertJsonPath('approved_snapshot.totals.net_delivery', '19800.00')->assertJsonPath('approved_snapshot.rate.factor', '80.0000')->assertJsonPath('approved_by', $f['commercial']->id)->assertJsonPath('pacted_date', null)->assertJsonMissingPath('authorization_proposal');
        $loan = LoanApplication::findOrFail($f['loan']['id']);
        $this->assertSame($this->canonicalObject($f['loan']['request_snapshot']), $this->canonicalObject($loan->request_snapshot));
        $this->assertNotNull($loan->approved_at);
        $this->assertDatabaseHas('loan_participations', ['loan_application_id' => $loan->id, 'client_id' => $f['clients'][0]->id, 'amount_requested' => 10000, 'amount_approved' => 9000]);
        $this->getJson($base.'/authorization')->assertOk()->assertJsonCount(2, 'history')->assertJsonPath('history.0.actor_id', $f['commercial']->id);
        $this->postJson($base.'/authorization', ['version' => 2, 'decision' => 'approve', 'first_due_on' => now('America/Cancun')->addDays(7)->toDateString(), 'notes' => 'Reintento.'])->assertConflict();
        $this->getJson('/api/credit/applications?authorization_status=pending_authorization')->assertJsonCount(0, 'data');
        $this->getJson('/api/credit/applications?authorization_status=authorized')->assertJsonCount(1, 'data');
        $this->assertDatabaseCount('credit_authorization_events', 2);
    }

    public function test_returned_conditions_require_new_proposal_and_preserve_all_versions(): void
    {
        $f = $this->authorizationFixture();
        $base = '/api/credit/applications/'.$f['loan']['id'];
        $this->postJson($base.'/proposal', $f['proposal'])->assertOk();
        $this->postJson($base.'/proposal', $f['proposal'])->assertConflict();
        $p = $f['proposal'];
        $p['version'] = 1;
        $this->postJson($base.'/proposal', $p)->assertConflict();
        Sanctum::actingAs($f['commercial']);
        $this->postJson($base.'/authorization', ['version' => 0, 'decision' => 'approve', 'first_due_on' => now('America/Cancun')->addDays(7)->toDateString(), 'notes' => 'Vista anterior.'])->assertConflict();
        $this->postJson($base.'/authorization', ['version' => 1, 'decision' => 'return', 'notes' => 'Revisar importe y entrega bancaria.'])->assertOk()->assertJsonPath('status', 'returned');
        $this->postJson($base.'/authorization', ['version' => 2, 'decision' => 'approve', 'first_due_on' => now('America/Cancun')->addDays(7)->toDateString(), 'notes' => 'Sin nueva propuesta.'])->assertConflict();
        Sanctum::actingAs($f['manager']);
        $p['version'] = 2;
        $p['participants'][0]['amount_proposed'] = '8500';
        $p['disbursement_method'] = 'bank_deposit';
        $this->postJson($base.'/proposal', $p)->assertOk();
        Sanctum::actingAs($f['commercial']);
        $this->postJson($base.'/authorization', ['version' => 3, 'decision' => 'approve', 'first_due_on' => now('America/Cancun')->addDays(7)->toDateString(), 'notes' => 'Correcciones aceptadas.'])->assertOk();
        $this->getJson($base.'/authorization')->assertOk()->assertJsonCount(4, 'history')->assertJsonPath('history.0.proposal.totals.principal', '21500.00')->assertJsonPath('history.3.proposal.totals.principal', '22000.00');
    }

    public function test_authorization_permissions_are_separate_from_field_coordinator_and_manager(): void
    {
        $f = $this->authorizationFixture();
        $base = '/api/credit/applications/'.$f['loan']['id'];
        $this->postJson($base.'/proposal', $f['proposal'])->assertOk();
        $decision = ['version' => 1, 'decision' => 'approve', 'first_due_on' => now('America/Cancun')->addDays(7)->toDateString(), 'notes' => 'Solicitud.'];
        foreach ([$f['manager'], $f['reviewer'], $f['user']] as $user) {
            Sanctum::actingAs($user);
            $this->postJson($base.'/authorization', $decision)->assertForbidden();
        }
        $f['user']->syncRoles(['Coordinador']);
        Sanctum::actingAs($f['user']);
        $this->postJson($base.'/authorization', $decision)->assertForbidden();
        Sanctum::actingAs($f['commercial']);
        $this->postJson($base.'/proposal', $f['proposal'])->assertForbidden();
        $otherBranch = Branch::create(['name' => 'Otra oficina', 'is_active' => true]);
        $f['commercial']->forceFill(['branch_id' => $otherBranch->id])->save();
        $this->getJson($base.'/authorization')->assertNotFound();
        $this->postJson($base.'/authorization', $decision)->assertNotFound();
        $this->getJson('/api/credit/applications?authorization_status=pending_authorization')->assertJsonCount(0, 'data');
        $f['commercial']->forceFill(['is_active' => false])->save();
        $this->postJson($base.'/authorization', $decision)->assertForbidden();
    }

    public function test_proposal_requires_complete_review_all_members_valid_amounts_and_both_methods(): void
    {
        $f = $this->authorizationFixture();
        $base = '/api/credit/applications/'.$f['loan']['id'];
        $p = $f['proposal'];
        unset($p['repayment_method']);
        $this->postJson($base.'/proposal', $p)->assertUnprocessable();
        foreach (['0', '10000.01', '9000.001', '-1'] as $amount) {
            $p = $f['proposal'];
            $p['participants'][0]['amount_proposed'] = $amount;
            $this->postJson($base.'/proposal', $p)->assertUnprocessable();
        }
        $p = $f['proposal'];
        $p['participants'][0]['client_id'] = (string) Str::uuid();
        $this->postJson($base.'/proposal', $p)->assertUnprocessable();
        $p = $f['proposal'];
        $p['participants'][0] = $p['participants'][1];
        $this->postJson($base.'/proposal', $p)->assertUnprocessable();
        $p = $f['proposal'];
        $p['total_approved'] = '1';
        $this->postJson($base.'/proposal', $p)->assertUnprocessable();
        LoanApplication::findOrFail($f['loan']['id'])->forceFill(['review_status' => 'in_review'])->save();
        $this->postJson($base.'/proposal', $f['proposal'])->assertConflict();
        $this->assertDatabaseCount('credit_authorization_events', 0);
    }

    public function test_authorization_revalidates_mora_files_and_participations_atomically(): void
    {
        $f = $this->authorizationFixture();
        $base = '/api/credit/applications/'.$f['loan']['id'];
        $this->postJson($base.'/proposal', $f['proposal'])->assertOk();
        Sanctum::actingAs($f['commercial']);
        $decision = ['version' => 1, 'decision' => 'approve', 'first_due_on' => now('America/Cancun')->addDays(7)->toDateString(), 'notes' => 'Autorización.'];
        $f['clients'][0]->update(['status' => 'moroso']);
        $this->postJson($base.'/authorization', $decision)->assertUnprocessable();
        $f['clients'][0]->update(['status' => 'activo']);
        $file = ClientApplication::where('client_id', $f['clients'][0]->id)->firstOrFail();
        $file->update(['version' => $file->version + 1]);
        $this->postJson($base.'/authorization', $decision)->assertUnprocessable();
        $file->update(['version' => $file->version - 1]);
        LoanParticipation::where('loan_application_id', $f['loan']['id'])->where('client_id', $f['clients'][2]->id)->delete();
        $this->postJson($base.'/authorization', $decision)->assertConflict();
        $this->assertSame(0, LoanParticipation::whereNotNull('amount_approved')->count());
        $this->assertDatabaseCount('credit_authorization_events', 1);
        $this->assertDatabaseHas('loan_applications', ['id' => $f['loan']['id'], 'status' => 'pendiente', 'total_approved' => null]);
    }

    public function test_authorization_rechecks_other_credits_and_group_committee(): void
    {
        $f = $this->authorizationFixture();
        $base = '/api/credit/applications/'.$f['loan']['id'];
        $this->postJson($base.'/proposal', $f['proposal'])->assertOk();
        Sanctum::actingAs($f['commercial']);
        $decision = ['version' => 1, 'decision' => 'approve', 'first_due_on' => now('America/Cancun')->addDays(7)->toDateString(), 'notes' => 'Revalidación.'];
        TeamMembership::where('client_id', $f['clients'][0]->id)->update(['role_in_group' => 'integrante']);
        $this->postJson($base.'/authorization', $decision)->assertUnprocessable();
        TeamMembership::where('client_id', $f['clients'][0]->id)->update(['role_in_group' => 'presidenta']);
        $other = Team::create(['branch_id' => $f['branch']->id, 'name' => 'Otro grupo', 'promoter_id' => $f['user']->id]);
        $loan = LoanApplication::create(['branch_id' => $f['branch']->id, 'team_id' => $other->id, 'promoter_id' => $f['user']->id, 'rate_id' => $f['rate']->id, 'status' => 'entregado']);
        LoanParticipation::create(['loan_application_id' => $loan->id, 'client_id' => $f['clients'][0]->id, 'amount_requested' => 1000]);
        $this->postJson($base.'/authorization', $decision)->assertUnprocessable();
        $this->assertDatabaseCount('credit_authorization_events', 1);
    }

    public function test_authorized_terms_cannot_be_overwritten_and_private_proposal_is_not_exposed(): void
    {
        $f = $this->authorizationFixture();
        $base = '/api/credit/applications/'.$f['loan']['id'];
        $this->postJson($base.'/proposal', $f['proposal'])->assertOk();
        Sanctum::actingAs($f['user']);
        $this->getJson($base.'/authorization')->assertForbidden();
        $this->getJson($base)->assertOk()->assertJsonMissingPath('authorization_proposal');
        Sanctum::actingAs($f['commercial']);
        $this->postJson($base.'/authorization', ['version' => 1, 'decision' => 'approve', 'first_due_on' => now('America/Cancun')->addDays(7)->toDateString(), 'notes' => 'Autorizado.', 'participants' => []])->assertUnprocessable();
        $this->postJson($base.'/authorization', ['version' => 1, 'decision' => 'approve', 'first_due_on' => now('America/Cancun')->addDays(7)->toDateString(), 'notes' => 'Autorizado.'])->assertOk();
        $loan = LoanApplication::findOrFail($f['loan']['id']);
        foreach ([fn () => $loan->update(['total_approved' => 1]), fn () => $loan->participations()->firstOrFail()->update(['amount_approved' => 1])] as $change) {
            try {
                $change();
                $this->fail('La autorización debe ser inmutable.');
            } catch (\LogicException $e) {
                $this->assertStringContainsString('inmutable', $e->getMessage());
            }
        }
    }

    public function test_approval_requires_valid_future_first_payment_and_preserves_it_in_history(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-28 12:00:00', 'America/Cancun'));
        $f = $this->authorizationFixture();
        $base = '/api/credit/applications/'.$f['loan']['id'];
        $this->postJson($base.'/proposal', $f['proposal'])->assertOk();
        Sanctum::actingAs($f['commercial']);
        $decision = ['version' => 1, 'decision' => 'approve', 'notes' => 'Fecha convenida con el grupo.'];
        $this->postJson($base.'/authorization', $decision)->assertUnprocessable()->assertJsonValidationErrors('first_due_on');
        foreach (['2026-12-28', '2026-12-27', '2027-02-30', '9999-12-31'] as $date) {
            $this->postJson($base.'/authorization', $decision + ['first_due_on' => $date])->assertUnprocessable()->assertJsonValidationErrors('first_due_on');
        }
        $this->assertDatabaseCount('credit_disbursements', 0);
        $this->assertDatabaseCount('loan_installments', 0);
        $this->postJson($base.'/authorization', $decision + ['first_due_on' => '2027-01-02'])->assertOk();
        $this->getJson($base)->assertOk()->assertJsonPath('approved_snapshot.first_due_on', '2027-01-02');
        $this->getJson($base.'/authorization')->assertOk()->assertJsonPath('history.0.proposal.first_due_on', '2027-01-02');
        $loan = LoanApplication::findOrFail($f['loan']['id']);
        $snapshot = $loan->approved_snapshot;
        $snapshot['first_due_on'] = '2027-01-09';
        $this->expectException(\LogicException::class);
        $loan->forceFill(['approved_snapshot' => $snapshot])->save();
    }

    public function test_delivery_cannot_replace_agreed_date_or_move_it_after_delayed_delivery(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-28 12:00:00', 'America/Cancun'));
        $f = $this->deliveryFixture();
        $p = $f['delivery'];
        $p['first_due_on'] = '2027-01-05';
        $this->postJson($f['base'].'/disbursement', $p)->assertUnprocessable()->assertJsonValidationErrors('first_due_on');
        $this->assertDatabaseCount('loan_installments', 0);
        $this->travel(7)->days();
        $p = $f['delivery'];
        $p['disbursed_on'] = now('America/Cancun')->toDateString();
        $this->postJson($f['base'].'/disbursement', $p)->assertUnprocessable()->assertJsonValidationErrors('first_due_on');
        $this->postJson($f['base'].'/disbursement', $f['delivery'])->assertOk()
            ->assertJsonPath('record.first_due_on', '2027-01-04');
        $dates = DB::table('loan_installments')->where('client_id', $f['clients'][0]->id)->orderBy('number')->pluck('due_on');
        $this->assertCount(16, $dates);
        foreach ($dates as $index => $date) {
            $this->assertSame(CarbonImmutable::parse('2027-01-04')->addWeeks($index)->toDateString(), $date);
        }
    }

    public function test_existing_approval_without_agreed_date_can_still_be_disbursed(): void
    {
        $f = $this->deliveryFixture();
        $loan = LoanApplication::findOrFail($f['loan']['id']);
        $oldSnapshot = $loan->approved_snapshot;
        unset($oldSnapshot['first_due_on']);
        // Simulate a historical approval created before this feature, without rewriting it through the application.
        DB::table('loan_applications')->where('id', $loan->id)->update(['approved_snapshot' => json_encode($oldSnapshot)]);
        $p = $f['delivery'];
        $p['first_due_on'] = CarbonImmutable::parse($p['first_due_on'])->addDays(2)->toDateString();
        $this->postJson($f['base'].'/disbursement', $p)->assertOk()->assertJsonPath('record.first_due_on', $p['first_due_on']);
    }

    private function deliveryFixture(string $method = 'cash_branch', string $repayment = 'bank_deposit'): array
    {
        $f = $this->authorizationFixture();
        $base = '/api/credit/applications/'.$f['loan']['id'];
        $f['proposal']['disbursement_method'] = $method;
        $f['proposal']['repayment_method'] = $repayment;
        $this->postJson($base.'/proposal', $f['proposal'])->assertOk();
        Sanctum::actingAs($f['commercial']);
        $this->postJson($base.'/authorization', ['version' => 1, 'decision' => 'approve', 'first_due_on' => now('America/Cancun')->addDays(7)->toDateString(), 'notes' => 'Autorizado.'])->assertOk();
        $cashier = User::factory()->create(['branch_id' => $f['branch']->id, 'is_active' => true]);
        $cashier->assignRole('Caja');
        Sanctum::actingAs($cashier);
        $today = now('America/Cancun');
        $delivery = ['id' => (string) Str::uuid(), 'authorization_version' => 2, 'disbursed_on' => $today->toDateString(), 'first_due_on' => $today->copy()->addDays(7)->toDateString(), 'notes' => 'Entrega cotejada con el recibo firmado.', 'confirmed' => true];
        if ($method === 'bank_deposit') {
            $delivery += ['bank_name' => 'Banco de prueba', 'bank_reference' => 'ENTREGA-001'];
        }

        return $f + compact('cashier', 'base', 'delivery');
    }

    public function test_disbursement_records_net_guarantee_and_exactly_sixteen_installments_per_member(): void
    {
        $f = $this->deliveryFixture();
        $result = $this->postJson($f['base'].'/disbursement', $f['delivery'])->assertOk()->assertJsonCount(48, 'installments')->assertJsonPath('record.actor_id', $f['cashier']->id)->json();
        $this->assertDatabaseHas('credit_disbursements', ['loan_application_id' => $f['loan']['id'], 'capital' => 22000, 'guarantee_retained' => 2200, 'net_delivered' => 19800, 'method' => 'cash_branch']);
        $this->getJson($f['base'])->assertOk()->assertJsonPath('status', 'entregado')->assertJsonPath('total_requested', '23000.00')->assertJsonPath('total_approved', '22000.00');
        $this->assertEquals(28160, DB::table('loan_installments')->sum('amount_due'));
        $this->assertEquals(0, DB::table('loan_installments')->sum('amount_paid'));
        $this->assertSame($f['delivery']['first_due_on'], $result['installments'][0]['due_on']);
        $last = CarbonImmutable::parse($f['delivery']['first_due_on'])->addWeeks(15)->toDateString();
        $this->assertSame($last, $result['installments'][47]['due_on']);
        $this->getJson('/api/credit/applications?financial_status=aprobado')->assertJsonCount(0, 'data');
        $this->getJson('/api/credit/applications?financial_status=entregado')->assertJsonCount(1, 'data');
        $this->postJson($f['base'].'/disbursement', $f['delivery'])->assertOk()->assertJsonCount(48, 'installments');
        $this->assertDatabaseCount('credit_disbursements', 1);
        $this->assertDatabaseCount('loan_installments', 48);
        $p = $f['delivery'];
        $p['notes'] = 'Diferente';
        $this->postJson($f['base'].'/disbursement', $p)->assertConflict();
        $p = $f['delivery'];
        $p['id'] = (string) Str::uuid();
        $this->postJson($f['base'].'/disbursement', $p)->assertConflict();
    }

    public function test_delivery_validates_dates_bank_reference_and_confirmation(): void
    {
        $f = $this->deliveryFixture('bank_deposit');
        $p = $f['delivery'];
        unset($p['bank_reference']);
        $this->postJson($f['base'].'/disbursement', $p)->assertUnprocessable();
        foreach (['confirmed' => false, 'authorization_version' => 1, 'first_due_on' => $f['delivery']['disbursed_on'], 'disbursed_on' => now('America/Cancun')->addDay()->toDateString()] as $key => $value) {
            $p = $f['delivery'];
            $p[$key] = $value;
            $response = $this->postJson($f['base'].'/disbursement', $p);
            if ($key === 'authorization_version') {
                $response->assertConflict();
            } else {
                $response->assertUnprocessable();
            }
        }
        $p = $f['delivery'];
        $p['net_delivered'] = '1';
        $this->postJson($f['base'].'/disbursement', $p)->assertUnprocessable();
        $p = $f['delivery'];
        $p['disbursed_on'] = now('America/Cancun')->subDay()->toDateString();
        $this->postJson($f['base'].'/disbursement', $p)->assertUnprocessable();
        $this->assertDatabaseCount('credit_disbursements', 0);
        $this->postJson($f['base'].'/disbursement', $f['delivery'])->assertOk()->assertJsonPath('record.bank_reference', 'ENTREGA-001');
        Sanctum::actingAs($f['user']);
        $this->getJson($f['base'].'/disbursement')->assertOk()->assertJsonMissingPath('record.bank_reference')->assertJsonMissingPath('record.notes')->assertJsonMissingPath('record.request_hash');
    }

    public function test_delivery_enforces_permissions_branch_eligibility_and_transaction_rollback(): void
    {
        $f = $this->deliveryFixture();
        Sanctum::actingAs($f['commercial']);
        $this->postJson($f['base'].'/disbursement', $f['delivery'])->assertForbidden();
        Sanctum::actingAs($f['user']);
        $this->postJson($f['base'].'/disbursement', $f['delivery'])->assertForbidden();
        Sanctum::actingAs($f['cashier']);
        $other = Branch::create(['name' => 'Otra sucursal', 'is_active' => true]);
        $f['cashier']->forceFill(['branch_id' => $other->id])->save();
        $this->postJson($f['base'].'/disbursement', $f['delivery'])->assertNotFound();
        $f['cashier']->forceFill(['branch_id' => $f['branch']->id])->save();
        $f['clients'][0]->update(['status' => 'moroso']);
        $this->postJson($f['base'].'/disbursement', $f['delivery'])->assertUnprocessable();
        $f['clients'][0]->update(['status' => 'activo']);
        $part = LoanParticipation::where('loan_application_id', $f['loan']['id'])->firstOrFail();
        DB::table('loan_installments')->insert(['id' => (string) Str::uuid(), 'branch_id' => $f['branch']->id, 'loan_application_id' => $f['loan']['id'], 'loan_participation_id' => $part->id, 'client_id' => $part->client_id, 'number' => 16, 'due_on' => $f['delivery']['first_due_on'], 'amount_due' => 1, 'created_at' => now()]);
        $this->postJson($f['base'].'/disbursement', $f['delivery'])->assertConflict();
        $this->assertDatabaseCount('credit_disbursements', 0);
        $this->assertDatabaseCount('loan_installments', 1);
        $this->assertDatabaseHas('loan_applications', ['id' => $f['loan']['id'], 'status' => 'aprobado']);
    }

    public function test_weekly_schedule_preserves_leap_days_and_weekends(): void
    {
        $rows = app(CreditDisbursement::class)->schedule(['members' => [['client_id' => 'example', 'estimate' => ['weekly_payment' => '800.00']]]], '2028-02-26');
        $this->assertCount(16, $rows);
        $this->assertSame('2028-03-04', $rows[1]['due_on']);
        $this->assertSame('2028-06-10', $rows[15]['due_on']);
        $this->assertEquals(12800, array_sum(array_column($rows, 'amount_due')));
    }

    private function collectionFixture(string $method = 'bank_deposit'): array
    {
        $f = $this->deliveryFixture('cash_branch', $method);
        $this->postJson($f['base'].'/disbursement', $f['delivery'])->assertOk();
        $state = $this->getJson($f['base'].'/payments')->assertOk()->json();
        $payment = ['id' => (string) Str::uuid(), 'balance_version' => $state['balance_version'], 'amount' => '500', 'method' => $method, 'confirmed' => true, 'contributions' => [['client_id' => $f['clients'][0]->id, 'payer_client_id' => $f['clients'][0]->id, 'amount' => '500']]];
        if ($method === 'bank_deposit') {
            $payment += ['bank_name' => 'Banco Demo', 'bank_reference' => 'FOLIO-001', 'deposited_on' => now('America/Cancun')->toDateString()];
        }

        return $f + compact('payment');
    }

    public function test_guarantee_waits_until_week_sixteen_is_separate_from_cash_and_reversible(): void
    {
        $this->freezeTime();
        $f = $this->collectionFixture('cash_branch');
        $url = $f['base'].'/guarantee';
        $initial = $this->getJson($url)->assertOk()->assertJsonPath('eligible_date', false)->assertJsonPath('members.0.retained', '900.00')->json();
        $input = ['id' => (string) Str::uuid(), 'version' => $initial['version'], 'reason' => 'Aplicación al final del ciclo.'];
        $this->postJson($url, $input)->assertForbidden();
        Sanctum::actingAs($f['commercial']);
        $this->postJson($url, $input)->assertUnprocessable();
        $this->travelTo(CarbonImmutable::parse($initial['available_on'], 'America/Cancun')->startOfDay());
        $this->renewStaffFixturePasswords($f);
        $this->postJson($url, $input)->assertOk()->assertJsonPath('members.0.applied', '900.00')->assertJsonPath('members.0.available', '0.00')->assertJsonPath('members.0.balance', '10620.00');
        $this->postJson($url, $input)->assertOk();
        $this->assertDatabaseCount('guarantee_applications', 1);
        $this->assertDatabaseCount('credit_payments', 0);
        $this->assertDatabaseCount('cash_closings', 0);
        $this->getJson($f['base'].'/statement')->assertOk()->assertJsonPath('members.0.paid', '900.00')->assertJsonPath('guarantees.0.amount', '2200.00')->assertJsonMissingPath('guarantees.0.reason');
        $stale = $input;
        $stale['id'] = (string) Str::uuid();
        $this->postJson($url, $stale)->assertConflict();
        $this->postJson('/api/guarantees/'.$input['id'].'/reverse', ['reason' => 'Corrección de la aplicación registrada.'])->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('members.0.available', '900.00')->assertJsonPath('members.0.balance', '11520.00');
        $this->getJson($f['base'].'/statement')->assertOk()->assertJsonPath('members.0.paid', '0.00')->assertJsonPath('guarantees.0.reversed_on', now('America/Cancun')->toDateString());
        $this->postJson('/api/guarantees/'.$input['id'].'/reverse', ['reason' => 'No duplicar este reverso.'])->assertConflict();
        $other = User::factory()->create(['branch_id' => Branch::create(['name' => 'Otra', 'is_active' => true])->id, 'is_active' => true]);
        $other->assignRole('Coordinación Comercial');
        Sanctum::actingAs($other);
        $this->getJson($url)->assertNotFound();
        $this->postJson($url, $input)->assertNotFound();
    }

    public function test_guarantee_only_applies_the_remaining_debt_preserves_excess_and_closes_credit(): void
    {
        $f = $this->collectionFixture('cash_branch');
        $state = $this->getJson($f['base'].'/payments')->assertOk()->json();
        $p = $f['payment'];
        $p['amount'] = '28060.00';
        $p['balance_version'] = $state['balance_version'];
        $p['contributions'] = [];
        foreach ($state['members'] as $index => $m) {
            $p['contributions'][] = ['client_id' => $m['client_id'], 'payer_client_id' => $m['client_id'], 'amount' => CreditCalculator::money(CreditCalculator::scaled($m['balance']) - ($index === 0 ? 10000 : 0))];
        }
        $this->postJson($f['base'].'/payments', $p)->assertOk();
        $url = $f['base'].'/guarantee';
        $g = $this->getJson($url)->assertOk()->json();
        $this->travelTo(CarbonImmutable::parse($g['available_on'], 'America/Cancun')->startOfDay());
        $this->renewStaffFixturePasswords($f);
        Sanctum::actingAs($f['commercial']);
        $input = ['id' => (string) Str::uuid(), 'version' => $g['version'], 'reason' => 'Aplicar únicamente el adeudo restante.'];
        $this->postJson($url, $input)->assertOk()->assertJsonPath('status', 'pagado')->assertJsonPath('members.0.applied', '100.00')->assertJsonPath('members.0.available', '800.00')->assertJsonPath('members.1.available', '800.00');
        $this->getJson($f['base'].'/statement')->assertOk()->assertJsonPath('members.0.balance', '0.00');
        $new = $this->getJson($url)->json();
        $input['id'] = (string) Str::uuid();
        $input['version'] = $new['version'];
        $this->postJson($url, $input)->assertUnprocessable();
        $this->assertDatabaseCount('credit_payments', 1);
    }

    public function test_late_fee_preview_uses_historical_bases_without_creating_collectible_charges(): void
    {
        $this->freezeTime();
        $f = $this->collectionFixture('cash_branch');
        $first = CarbonImmutable::parse($f['delivery']['first_due_on'], 'America/Cancun');
        $this->travelTo($first->startOfDay());
        $this->getJson($f['base'].'/statement')->assertOk()->assertJsonPath('late_fee_preview.total', '0.00');
        $this->travelTo($first->addDay()->startOfDay());
        $before = $this->getJson($f['base'].'/statement')->assertOk()
            ->assertJsonPath('late_fee_preview.mode', 'simulation')
            ->assertJsonPath('late_fee_preview.total', '176.00')->json();
        $this->assertSame('28160.00', CreditCalculator::money(array_sum(array_map(fn ($m) => CreditCalculator::scaled($m['balance']), $before['members']))));
        // Same-day payment occurs after the first weekly assessment, and only reduces later bases.
        $this->postJson($f['base'].'/payments', $f['payment'])->assertOk();
        $this->getJson($f['base'].'/statement')->assertOk()->assertJsonPath('late_fee_preview.total', '176.00');
        $this->travelTo($first->addDays(8)->startOfDay());
        $preview = $this->getJson($f['base'].'/statement')->assertOk()->assertJsonPath('late_fee_preview.total', '478.00')->json('late_fee_preview');
        $individual = collect($preview['installments'])->first(fn ($row) => $row['client_id'] === $f['clients'][0]->id && $row['number'] === 1);
        $this->assertSame('94.00', $individual['amount']);
        $this->assertSame(['720.00', '220.00'], array_column($individual['charges'], 'base'));
        $this->assertSame(['72.00', '22.00'], array_column($individual['charges'], 'amount'));
        $this->getJson($f['base'].'/statement')->assertOk()->assertJsonPath('late_fee_preview', $preview);
        $this->assertDatabaseCount('credit_payments', 1);
        $this->assertDatabaseCount('cash_closings', 0);
        $this->assertDatabaseHas('loan_installments', ['loan_application_id' => $f['loan']['id'], 'client_id' => $f['clients'][0]->id, 'number' => 1, 'amount_due' => 720, 'amount_paid' => 500]);
        $v = $this->reversalInput($f);
        Sanctum::actingAs($f['manager']);
        $this->postJson('/api/credit/receipts/'.$f['payment']['id'].'/reverse', $v)->assertOk();
        $this->getJson($f['base'].'/statement')->assertOk()->assertJsonPath('late_fee_preview.total', '528.00');
        $other = Branch::create(['name' => 'Otra para mora', 'is_active' => true]);
        $f['manager']->forceFill(['branch_id' => $other->id])->save();
        $this->getJson($f['base'].'/statement')->assertNotFound();
    }

    public function test_payment_before_arrears_reduces_first_week_and_full_payment_stops_future_mora(): void
    {
        $this->freezeTime();
        $f = $this->collectionFixture('cash_branch');
        $first = CarbonImmutable::parse($f['delivery']['first_due_on'], 'America/Cancun');
        $this->postJson($f['base'].'/payments', $f['payment'])->assertOk();
        $this->travelTo($first->addDay()->startOfDay());
        $this->getJson($f['base'].'/statement')->assertOk()->assertJsonPath('late_fee_preview.total', '126.00');
        $state = $this->getJson($f['base'].'/payments')->assertOk()->json();
        $payment = $f['payment'];
        $payment['id'] = (string) Str::uuid();
        $payment['balance_version'] = $state['balance_version'];
        $payment['amount'] = '220.00';
        $payment['contributions'][0]['amount'] = '220.00';
        $this->postJson($f['base'].'/payments', $payment)->assertOk();
        $this->travelTo($first->addDays(8)->startOfDay());
        $preview = $this->getJson($f['base'].'/statement')->assertOk()->json('late_fee_preview');
        $row = collect($preview['installments'])->first(fn ($row) => $row['client_id'] === $f['clients'][0]->id && $row['number'] === 1);
        $this->assertSame('22.00', $row['amount']);
        $this->assertCount(1, $row['charges']);
    }

    public function test_late_fee_preview_accounts_for_guarantee_and_its_reversal(): void
    {
        $this->freezeTime();
        $f = $this->collectionFixture('cash_branch');
        $first = CarbonImmutable::parse($f['delivery']['first_due_on'], 'America/Cancun');
        $this->travelTo($first->addWeeks(15)->startOfDay());
        $this->renewStaffFixturePasswords($f);
        Sanctum::actingAs($f['commercial']);
        $url = $f['base'].'/guarantee';
        $state = $this->getJson($url)->assertOk()->json();
        $id = (string) Str::uuid();
        $this->postJson($url, ['id' => $id, 'version' => $state['version'], 'reason' => 'Aplicación al finalizar la semana dieciséis.'])->assertOk();
        $this->travelTo($first->addDays(106)->startOfDay());
        $this->renewStaffFixturePasswords($f);
        $this->getJson($f['base'].'/statement')->assertOk()->assertJsonPath('late_fee_preview.total', '23716.00');
        $this->postJson('/api/guarantees/'.$id.'/reverse', ['reason' => 'Corregir aplicación de garantía registrada por error.'])->assertOk();
        $this->getJson($f['base'].'/statement')->assertOk()->assertJsonPath('late_fee_preview.total', '23936.00');
        $this->assertDatabaseCount('credit_payments', 0);
    }

    public function test_new_policy_collects_due_then_fees_then_advances_and_reverses_closed_payment(): void
    {
        $this->freezeTime();
        config(['late_fees.new_authorizations' => true]);
        $f = $this->collectionFixture('cash_branch');
        $this->assertSame('weekly-started-10-percent-v1', LoanApplication::find($f['loan']['id'])->approved_snapshot['late_fee_policy']['id']);
        $this->travelTo(CarbonImmutable::parse($f['delivery']['first_due_on'], 'America/Cancun')->addDay()->startOfDay());
        $state = $this->getJson($f['base'].'/payments')->assertOk()->assertJsonPath('late_fees.balance', '176.00')->json();
        $payment = $f['payment'];
        $payment['balance_version'] = $state['balance_version'];
        $payment['amount'] = $payment['contributions'][0]['amount'] = '1000.00';
        $this->postJson($f['base'].'/payments', $payment)->assertOk()->assertJsonPath('late_fees.paid', '72.00');
        $this->postJson($f['base'].'/payments', $payment)->assertOk();
        $this->assertDatabaseCount('credit_payments', 1);
        $this->assertDatabaseCount('weekly_late_fees', 3);
        $this->assertDatabaseHas('loan_installments', ['loan_application_id' => $f['loan']['id'], 'client_id' => $f['clients'][0]->id, 'number' => 1, 'amount_paid' => 720]);
        $this->assertDatabaseHas('loan_installments', ['loan_application_id' => $f['loan']['id'], 'client_id' => $f['clients'][0]->id, 'number' => 2, 'amount_paid' => 208]);
        $this->getJson('/api/credit/receipts/'.$payment['id'])->assertOk()->assertJsonPath('installment_amount', '928.00')->assertJsonPath('late_fee_amount', '72.00')->assertJsonPath('allocations.2.kind', 'late_fee');
        $this->getJson($f['base'].'/statement')->assertOk()->assertJsonPath('late_fees.balance', '104.00')->assertJsonPath('late_fee_preview', null);
        $date = now('America/Cancun')->toDateString();
        $preview = $this->getJson('/api/cash/preview?branch_id='.$f['branch']->id.'&business_date='.$date)->assertOk()->assertJsonPath('cash_total', '1000.00')->assertJsonPath('payments.data.0.late_fee_amount', '72.00')->json();
        $closing = ['id' => (string) Str::uuid(), 'branch_id' => $f['branch']->id, 'business_date' => $date, 'version' => $preview['version'], 'cash_counted' => '1000.00', 'notes' => '', 'confirmed' => true];
        $this->postJson('/api/cash/closings', $closing)->assertOk();
        $f['payment'] = $payment;
        $reversal = $this->reversalInput($f);
        Sanctum::actingAs($f['manager']);
        $this->postJson('/api/credit/receipts/'.$payment['id'].'/reverse', $reversal)->assertOk();
        $this->getJson($f['base'].'/statement')->assertOk()->assertJsonPath('late_fees.paid', '0.00')->assertJsonPath('late_fees.balance', '176.00');
        $this->getJson('/api/cash/closings/'.$closing['id'].'/report')->assertOk()->assertJsonPath('record.cash_total', '1000.00')->assertJsonPath('payments.0.late_fee_amount', '72.00')->assertJsonPath('adjustments.0.late_fee_amount', '72.00');
        $this->assertDatabaseCount('weekly_late_fees', 3);
        $this->assertDatabaseCount('late_fee_payment_allocations', 1);
    }

    public function test_new_policy_handles_fee_only_bank_payment_and_keeps_credit_open_until_paid(): void
    {
        $this->freezeTime();
        config(['late_fees.new_authorizations' => true]);
        $f = $this->collectionFixture('bank_deposit');
        $this->travelTo(CarbonImmutable::parse($f['delivery']['first_due_on'], 'America/Cancun')->addDays(106)->startOfDay());
        $this->renewStaffFixturePasswords($f);
        $state = $this->getJson($f['base'].'/payments')->assertOk()->json();
        $payment = $f['payment'];
        $payment['balance_version'] = $state['balance_version'];
        $payment['amount'] = '28160.00';
        $payment['contributions'] = array_map(fn ($m) => ['client_id' => $m['client_id'], 'payer_client_id' => $m['client_id'], 'amount' => $m['installment_balance']], $state['members']);
        $this->postJson($f['base'].'/payments', $payment)->assertOk()->assertJsonPath('status', 'entregado')->assertJsonPath('late_fees.balance', '23936.00');
        $this->getJson('/api/credit/portfolio?status=overdue')->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('summary.installment_balance', '0.00')->assertJsonPath('summary.balance', '23936.00')
            ->assertJsonPath('data.0.days_overdue', 106);
        $this->getJson('/api/credit/portfolio?status=current')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('portfolio.overdue_credits', 1)->assertJsonPath('portfolio.balance', '23936.00');
        $state = $this->getJson($f['base'].'/payments')->assertOk()->json();
        $feePayment = $payment;
        $feePayment['id'] = (string) Str::uuid();
        $feePayment['bank_reference'] = 'MORA-002';
        $feePayment['balance_version'] = $state['balance_version'];
        $feePayment['amount'] = '23936.00';
        $feePayment['contributions'] = array_map(fn ($m) => ['client_id' => $m['client_id'], 'payer_client_id' => $f['clients'][1]->id, 'amount' => $m['late_fee_balance']], $state['members']);
        $this->postJson($f['base'].'/payments', $feePayment)->assertOk()->assertJsonPath('status', 'pagado')->assertJsonPath('late_fees.balance', '0.00');
        $this->getJson('/api/credit/receipts/'.$feePayment['id'])->assertOk()->assertJsonPath('installment_amount', '0.00')->assertJsonPath('late_fee_amount', '23936.00');
        $this->getJson($f['base'].'/statement')->assertOk()->assertJsonPath('late_fees.paid', '23936.00')->assertJsonPath('late_fees.balance', '0.00');
        $this->getJson('/api/cash/preview?branch_id='.$f['branch']->id.'&business_date='.now('America/Cancun')->toDateString())->assertOk()->assertJsonPath('cash_total', '0.00')->assertJsonPath('bank_total', '52096.00');
        $f['payment'] = $feePayment;
        $reversal = $this->reversalInput($f);
        Sanctum::actingAs($f['manager']);
        $this->postJson('/api/credit/receipts/'.$feePayment['id'].'/reverse', $reversal)->assertOk();
        $this->getJson($f['base'].'/statement')->assertOk()->assertJsonPath('credit.status', 'entregado')->assertJsonPath('late_fees.balance', '23936.00');
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('portfolio.balance', '23936.00')->assertJsonPath('payments.total', '28160.00');
        $this->assertEquals(28160, DB::table('loan_installments')->sum('amount_paid'));
    }

    public function test_new_policy_requires_fresh_balance_and_does_not_rewrite_earlier_assessments(): void
    {
        $this->freezeTime();
        config(['late_fees.new_authorizations' => true]);
        $f = $this->collectionFixture('cash_branch');
        $first = CarbonImmutable::parse($f['delivery']['first_due_on'], 'America/Cancun');
        $this->travelTo($first->addDay()->startOfDay());
        $this->postJson($f['base'].'/payments', $f['payment'])->assertConflict();
        $payment = $f['payment'];
        $payment['balance_version'] = $this->getJson($f['base'].'/payments')->json('balance_version');
        $this->postJson($f['base'].'/payments', $payment)->assertOk();
        $this->travelTo($first->addDays(8)->startOfDay());
        $f['payment'] = $payment;
        $reversal = $this->reversalInput($f);
        Sanctum::actingAs($f['manager']);
        $this->postJson('/api/credit/receipts/'.$payment['id'].'/reverse', $reversal)->assertOk();
        // The second assessment of the first quota remains 22; reversal does not retroactively make it 72.
        $state = $this->getJson($f['base'].'/statement')->assertOk()->assertJsonPath('late_fees.total', '478.00')->json('late_fees');
        $second = collect($state['charges'])->first(fn ($r) => $r['client_id'] === $f['clients'][0]->id && $r['number'] === 1 && $r['charged_on'] === $first->addDays(8)->toDateString());
        $this->assertSame('22.00', $second['amount']);
        $this->assertSame('220.00', $second['base']);
        $this->assertDatabaseCount('weekly_late_fees', 9);
    }

    public function test_new_policy_guarantee_covers_quotas_without_waiving_fees_or_closing_loan(): void
    {
        $this->freezeTime();
        config(['late_fees.new_authorizations' => true]);
        $f = $this->collectionFixture('cash_branch');
        $this->travelTo(CarbonImmutable::parse($f['delivery']['first_due_on'], 'America/Cancun')->addDays(106)->startOfDay());
        $this->renewStaffFixturePasswords($f);
        $state = $this->getJson($f['base'].'/payments')->assertOk()->json();
        $payment = $f['payment'];
        $payment['balance_version'] = $state['balance_version'];
        $payment['contributions'] = array_map(fn ($m) => ['client_id' => $m['client_id'], 'payer_client_id' => $m['client_id'], 'amount' => CreditCalculator::money(CreditCalculator::scaled($m['installment_balance']) - 10000)], $state['members']);
        $payment['amount'] = '27860.00';
        $this->postJson($f['base'].'/payments', $payment)->assertOk();
        Sanctum::actingAs($f['commercial']);
        $url = $f['base'].'/guarantee';
        $state = $this->getJson($url)->assertOk()->json();
        $this->postJson($url, ['id' => (string) Str::uuid(), 'version' => $state['version'], 'reason' => 'Cubrir las últimas cuotas con la garantía retenida.'])->assertOk()->assertJsonPath('status', 'entregado');
        $statement = $this->getJson($f['base'].'/statement')->assertOk()->assertJsonPath('late_fees.balance', '23936.00')->json();
        $this->assertSame('0.00', $statement['members'][0]['installment_balance']);
        $this->assertGreaterThan(0, $statement['members'][0]['days_overdue']);
    }

    public function test_contract_and_single_group_note_preserve_terms_and_cannot_be_uploaded_as_signed(): void
    {
        config(['late_fees.new_authorizations' => true]);
        $f = $this->deliveryFixture();
        $url = '/api/documents/sources/credit/'.$f['loan']['id'];
        foreach (['contract_review', 'promissory_review'] as $kind) {
            $id = $this->postJson($url, ['kind' => $kind])->assertOk()->json('id');
            $record = DB::table('document_versions')->where('id', $id)->first();
            $snapshot = json_decode($record->snapshot, true);
            $this->assertSame('review_only', $snapshot['document_status']);
            $this->assertCount(16, $snapshot['schedule']);
            $this->assertStringContainsString('28160.00', $record->html);
            $this->assertStringContainsString('22000.00', $record->html);
            $this->assertStringContainsString('NO UTILIZAR PARA FIRMA', $record->html);
            $this->assertStringContainsString('10%', $record->html);
            $this->assertStringNotContainsString('10% mensual', $record->html);
            $this->postJson($url, ['kind' => $kind])->assertOk()->assertJsonPath('id', $id);
            $this->postJson('/api/documents/versions/'.$id.'/signed', [])->assertUnprocessable();
            $f['team']->update(['name' => 'Nombre modificado después de emitir']);
            $this->getJson('/api/documents/versions/'.$id)->assertOk()->assertJsonPath('html', $record->html)->assertJsonPath('sha256', hash('sha256', $record->html));
            if ($directory = getenv('DOCUMENT_REVIEW_PREVIEW_DIR')) {
                file_put_contents($directory.'/'.$kind.'.html', $record->html);
            }
        }
        $this->assertDatabaseCount('document_versions', 2);
        $this->assertDatabaseCount('document_signed_files', 0);
        $other = User::factory()->create(['branch_id' => Branch::create(['name' => 'Otra', 'is_active' => true])->id, 'is_active' => true]);
        $other->assignRole('Gerente');
        Sanctum::actingAs($other);
        $this->postJson($url, ['kind' => 'promissory_review'])->assertNotFound();
        $this->getJson('/api/documents/versions/'.$id)->assertNotFound();
    }

    public function test_confirmed_group_note_issues_current_template_without_replacing_previous_document(): void
    {
        $f = $this->deliveryFixture();
        $url = '/api/documents/sources/credit/'.$f['loan']['id'];
        $id = $this->postJson($url, ['kind' => 'promissory_review'])->assertOk()->json('id');
        $record = (array) DB::table('document_versions')->where('id', $id)->first();
        $snapshot = json_decode($record['snapshot'], true);
        $this->assertSame(3, $record['template_version']);
        $this->assertSame('28160.00', $snapshot['promissory_terms']['amount']);
        $this->assertSame('principal_plus_ordinary_interest', $snapshot['promissory_terms']['amount_basis']);
        $this->assertSame(CarbonImmutable::parse($f['delivery']['first_due_on'])->addWeeks(15)->toDateString(), $snapshot['promissory_terms']['due_on']);
        $old = (string) Str::uuid();
        DB::table('document_versions')->insert(array_merge($record, ['id' => $old, 'template_version' => 1, 'html' => 'Documento anterior conservado', 'sha256' => hash('sha256', 'Documento anterior conservado')]));
        $this->postJson($url, ['kind' => 'promissory_review'])->assertOk()->assertJsonPath('id', $id);
        $this->getJson('/api/documents/versions/'.$old)->assertOk()->assertJsonPath('html', 'Documento anterior conservado');
        $this->assertDatabaseCount('document_versions', 2);
    }

    public function test_review_documents_do_not_add_weekly_fees_to_historical_terms_and_require_dates(): void
    {
        $f = $this->deliveryFixture();
        $url = '/api/documents/sources/credit/'.$f['loan']['id'];
        $id = $this->postJson($url, ['kind' => 'promissory_review'])->assertOk()->json('id');
        $html = $this->getJson('/api/documents/versions/'.$id)->assertOk()->json('html');
        $this->assertStringContainsString('no incluye política de mora cobrable', $html);
        $this->assertStringNotContainsString('cargo simple del 10%', $html);
        $loan = LoanApplication::findOrFail($f['loan']['id']);
        $terms = $loan->approved_snapshot;
        unset($terms['first_due_on']);
        DB::table('loan_applications')->where('id', $loan->id)->update(['approved_snapshot' => json_encode($terms), 'authorization_version' => $loan->authorization_version + 1]);
        foreach (['contract_review', 'promissory_review'] as $kind) {
            $this->postJson($url, ['kind' => $kind])->assertConflict();
        }
        $this->assertDatabaseCount('document_versions', 1);
    }

    public function test_authorization_document_uses_frozen_credit_terms_and_date(): void
    {
        $f = $this->deliveryFixture();
        $url = '/api/documents/sources/credit/'.$f['loan']['id'];
        $id = $this->postJson($url, ['kind' => 'authorization'])->assertOk()->json('id');
        $html = $this->getJson('/api/documents/versions/'.$id)->assertOk()->json('html');
        $this->assertStringContainsString($f['delivery']['first_due_on'], $html);
        $this->assertStringContainsString('22000.00', $html);
        $this->postJson($url, ['kind' => 'authorization'])->assertOk()->assertJsonPath('id', $id);
        $this->assertDatabaseCount('document_versions', 1);
    }

    public function test_schedule_document_preserves_sixteen_authorized_dates_and_amounts(): void
    {
        $f = $this->deliveryFixture();
        $url = '/api/documents/sources/credit/'.$f['loan']['id'];
        $id = $this->postJson($url, ['kind' => 'schedule'])->assertOk()->json('id');
        $doc = DB::table('document_versions')->where('id', $id)->first();
        $snapshot = json_decode($doc->snapshot, true);
        $this->assertCount(16, $snapshot['schedule']);
        foreach ($snapshot['schedule'] as $index => $week) {
            $this->assertSame($index + 1, $week['number']);
            $this->assertSame(CarbonImmutable::parse($f['delivery']['first_due_on'])->addWeeks($index)->toDateString(), $week['due_on']);
            $this->assertSame('1760.00', $week['amount']);
        }
        $this->assertSame(28160.0, array_sum(array_column($snapshot['schedule'], 'amount')));
        $this->postJson($url, ['kind' => 'schedule'])->assertOk()->assertJsonPath('id', $id);
        $this->getJson('/api/documents/versions/'.$id)->assertOk()->assertJsonPath('sha256', hash('sha256', $doc->html));
        $this->assertStringContainsString('No acredita desembolso ni pagos recibidos', $doc->html);
        $loan = LoanApplication::findOrFail($f['loan']['id']);
        $terms = $loan->approved_snapshot;
        unset($terms['first_due_on']);
        // Simulate an imported historical authorization without a first due date.
        DB::table('loan_applications')->where('id', $loan->id)->update(['approved_snapshot' => json_encode($terms), 'authorization_version' => $loan->authorization_version + 1]);
        $this->postJson($url, ['kind' => 'schedule'])->assertConflict();
        $this->getJson('/api/documents/versions/'.$id)->assertOk()->assertJsonPath('html', $doc->html);
        $this->assertDatabaseCount('document_versions', 1);
    }

    public function test_portfolio_and_dashboard_project_mora_without_writes_and_respect_scope(): void
    {
        $this->freezeTime();
        config(['late_fees.new_authorizations' => true]);
        $f = $this->collectionFixture();
        $first = CarbonImmutable::parse($f['delivery']['first_due_on'], 'America/Cancun');
        $this->travelTo($first->startOfDay());
        $this->getJson('/api/credit/portfolio?status=today')->assertOk()->assertJsonPath('summary.late_fee_balance', '0.00')->assertJsonPath('summary.due_today', '1760.00');
        $this->travelTo($first->addDay()->startOfDay());
        $this->getJson('/api/credit/portfolio?status=overdue')->assertOk()
            ->assertJsonPath('summary.balance', '28336.00')->assertJsonPath('summary.installment_balance', '28160.00')
            ->assertJsonPath('summary.late_fee_balance', '176.00')->assertJsonPath('summary.overdue', '1936.00')
            ->assertJsonPath('summary.due', '1936.00')->assertJsonPath('summary.overdue_credits', 1)
            ->assertJsonPath('data.0.late_fee_balance', '176.00')->assertJsonPath('data.0.days_overdue', 1)
            ->assertJsonMissingPath('data.0.approved_snapshot');
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('portfolio.balance', '28336.00')->assertJsonPath('portfolio.late_fee_balance', '176.00')->assertJsonPath('payments.total', '0.00');
        $this->getJson('/api/credit/portfolio?status=current')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/credit/portfolio?search=NoExiste')->assertOk()->assertJsonPath('summary.late_fee_balance', '0.00');
        $this->travelTo($first->addDays(8)->startOfDay());
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('portfolio.late_fee_balance', '528.00');
        $this->assertDatabaseCount('weekly_late_fees', 0);
        $this->assertDatabaseCount('credit_payments', 0);
        $other = User::factory()->create(['branch_id' => $f['branch']->id, 'is_active' => true]);
        $other->assignRole('Promotor');
        Sanctum::actingAs($other);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('portfolio.balance', '0.00');
        $this->getJson('/api/credit/portfolio')->assertOk()->assertJsonPath('summary.late_fee_balance', '0.00');
        $branch = Branch::create(['name' => 'Otra sucursal', 'is_active' => true]);
        $manager = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $manager->assignRole('Gerente');
        Sanctum::actingAs($manager);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('portfolio.balance', '0.00');
        $this->getJson('/api/credit/portfolio')->assertOk()->assertJsonPath('total', 0);
        Sanctum::actingAs(User::factory()->create(['is_active' => true]));
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('portfolio', null);
    }

    public function test_portfolio_totals_cover_all_batches_and_pages_without_duplicate_fees(): void
    {
        $this->freezeTime();
        config(['late_fees.new_authorizations' => true]);
        $f = $this->collectionFixture();
        $this->travelTo(CarbonImmutable::parse($f['delivery']['first_due_on'], 'America/Cancun')->addDay()->startOfDay());
        // Reporting fixtures: duplicate the authorized schedule to cross the 100-loan batch boundary.
        $loan = (array) DB::table('loan_applications')->where('id', $f['loan']['id'])->first();
        $installments = DB::table('loan_installments')->where('loan_application_id', $f['loan']['id'])->get();
        $participations = DB::table('loan_participations')->where('loan_application_id', $f['loan']['id'])->get();
        for ($n = 1; $n <= 100; $n++) {
            $id = (string) Str::uuid();
            DB::table('loan_applications')->insert(array_merge($loan, ['id' => $id, 'folio' => 'BATCH-'.$n]));
            $participationIds = [];
            foreach ($participations as $participation) {
                $participationIds[$participation->id] = (string) Str::uuid();
                DB::table('loan_participations')->insert(array_merge((array) $participation, ['id' => $participationIds[$participation->id], 'loan_application_id' => $id]));
            }
            DB::table('loan_installments')->insert($installments->map(fn ($row) => array_merge((array) $row, ['id' => (string) Str::uuid(), 'loan_application_id' => $id, 'loan_participation_id' => $participationIds[$row->loan_participation_id]]))->all());
        }
        $this->getJson('/api/credit/portfolio?page=5')->assertOk()->assertJsonPath('total', 101)
            ->assertJsonPath('last_page', 5)->assertJsonCount(1, 'data')->assertJsonPath('summary.late_fee_balance', '17776.00')
            ->assertJsonPath('summary.balance', '2861936.00')->assertJsonPath('summary.overdue_credits', 101);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('portfolio.late_fee_balance', '17776.00');
        $csv = $this->get('/api/credit/portfolio/export?page=5')->assertOk()->streamedContent();
        $rows = array_map(fn ($line) => str_getcsv($line, ',', '"', ''), explode("\r\n", substr($csv, 3)));
        $this->assertCount(101, array_filter($rows, fn ($row) => count($row) === 12 && Str::isUuid($row[0])));
        $this->assertStringContainsString('TOTAL,,,,2844160.00,17776.00,2861936.00', $csv);
        $this->assertDatabaseCount('weekly_late_fees', 0);
    }

    public function test_portfolio_export_filters_scope_and_escapes_spreadsheet_formulas(): void
    {
        $this->freezeTime();
        config(['late_fees.new_authorizations' => true]);
        $f = $this->collectionFixture();
        $f['team']->update(['name' => '=SUM(1,2)']);
        $this->travelTo(CarbonImmutable::parse($f['delivery']['first_due_on'], 'America/Cancun')->addDay()->startOfDay());
        $response = $this->get('/api/credit/portfolio/export?status=overdue')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=SUM(1,2)", $csv);
        $this->assertStringContainsString('28160.00,176.00,28336.00,1936.00,0.00,1936.00', $csv);
        $this->assertStringContainsString('Créditos,0', $this->get('/api/credit/portfolio/export?status=current')->assertOk()->streamedContent());
        $this->getJson('/api/credit/portfolio/export?status=invalid')->assertUnprocessable();
        $other = User::factory()->create(['branch_id' => $f['branch']->id, 'is_active' => true]);
        $other->assignRole('Promotor');
        Sanctum::actingAs($other);
        $this->assertStringNotContainsString('SUM', $this->get('/api/credit/portfolio/export')->assertOk()->streamedContent());
        Sanctum::actingAs(User::factory()->create(['is_active' => true]));
        $this->getJson('/api/credit/portfolio/export')->assertForbidden();
        $this->assertDatabaseCount('weekly_late_fees', 0);
    }

    public function test_portfolio_tracks_due_dates_payments_reversals_and_filters(): void
    {
        $this->freezeTime();
        $f = $this->collectionFixture('cash_branch');
        $url = '/api/credit/portfolio';
        $this->getJson($url)->assertOk()->assertJsonPath('summary.balance', '28160.00')->assertJsonPath('summary.due', '0.00')->assertJsonPath('total', 1);
        $this->postJson($f['base'].'/payments', $f['payment'])->assertOk();
        $this->travelTo(CarbonImmutable::parse($f['delivery']['first_due_on'], 'America/Cancun')->startOfDay());
        $this->getJson($url.'?status=today')->assertOk()->assertJsonPath('summary.due_today', '1260.00')->assertJsonPath('summary.overdue', '0.00')->assertJsonPath('data.0.days_overdue', 0);
        $this->getJson($url.'?status=overdue')->assertOk()->assertJsonPath('total', 0);
        $this->travel(1)->days();
        $this->getJson($url.'?status=overdue')->assertOk()->assertJsonPath('summary.overdue', '1260.00')->assertJsonPath('data.0.days_overdue', 1)->assertJsonPath('summary.balance', '27660.00');
        $this->getJson($url.'?search=Flores')->assertOk()->assertJsonPath('total', 1);
        $this->getJson($url.'?search=NoExiste')->assertOk()->assertJsonPath('total', 0)->assertJsonPath('summary.balance', '0.00');
        $this->getJson($url.'?search=%25')->assertOk()->assertJsonPath('total', 0);
        $this->getJson($url.'?status=current')->assertOk()->assertJsonPath('total', 0);
        $this->getJson($url.'?page=2')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('summary.balance', '27660.00');
        $v = $this->reversalInput($f);
        Sanctum::actingAs($f['manager']);
        $this->postJson('/api/credit/receipts/'.$f['payment']['id'].'/reverse', $v)->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('summary.overdue', '1760.00')->assertJsonPath('summary.balance', '28160.00');
        $this->getJson($url.'?status=invalid')->assertUnprocessable();
        $this->getJson($url.'?page=-1')->assertUnprocessable();
    }

    public function test_portfolio_limits_rows_and_totals_to_authorized_scope(): void
    {
        $f = $this->collectionFixture();
        Sanctum::actingAs($f['user']);
        $this->getJson('/api/credit/portfolio')->assertOk()->assertJsonPath('total', 1)->assertJsonMissingPath('data.0.approved_snapshot');
        $other = User::factory()->create(['branch_id' => $f['branch']->id, 'is_active' => true]);
        $other->assignRole('Promotor');
        Sanctum::actingAs($other);
        $this->getJson('/api/credit/portfolio')->assertOk()->assertJsonPath('total', 0)->assertJsonPath('summary.balance', '0.00');
        $branch = Branch::create(['name' => 'Otra', 'is_active' => true]);
        $manager = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $manager->assignRole('Gerente');
        Sanctum::actingAs($manager);
        $this->getJson('/api/credit/portfolio')->assertOk()->assertJsonPath('total', 0);
        $none = User::factory()->create(['is_active' => true]);
        Sanctum::actingAs($none);
        $this->getJson('/api/credit/portfolio')->assertForbidden();
        $manager->is_active = false;
        Sanctum::actingAs($manager);
        $this->getJson('/api/credit/portfolio')->assertForbidden();
    }

    public function test_statement_preserves_approved_conditions_partial_payments_solidarity_and_reversals(): void
    {
        $this->freezeTime();
        $f = $this->collectionFixture('cash_branch');
        $url = $f['base'].'/statement';
        $this->getJson($url)->assertOk()->assertJsonCount(48, 'installments')
            ->assertJsonPath('members.0.principal', '9000.00')->assertJsonPath('members.0.total_repayment', '11520.00')
            ->assertJsonPath('members.0.paid', '0.00')->assertJsonPath('members.0.due', '0.00');
        $r = $this->postJson($f['base'].'/payments', $f['payment'])->assertOk()->json();
        $this->travelTo(CarbonImmutable::parse($f['delivery']['first_due_on'], 'America/Cancun')->startOfDay());
        $this->getJson($url)->assertOk()->assertJsonPath('members.0.due', '220.00')
            ->assertJsonPath('members.0.overdue', '0.00')->assertJsonPath('members.0.days_overdue', 0);
        $this->travel(1)->days();
        $this->getJson($url)->assertOk()->assertJsonPath('members.0.balance', '11020.00')
            ->assertJsonPath('members.0.overdue', '220.00')->assertJsonPath('members.0.days_overdue', 1);
        $p = $f['payment'];
        $p['id'] = (string) Str::uuid();
        $p['balance_version'] = $r['balance_version'];
        $p['amount'] = '940';
        $p['contributions'] = [['client_id' => $f['clients'][0]->id, 'payer_client_id' => $f['clients'][1]->id, 'amount' => '940']];
        $this->postJson($f['base'].'/payments', $p)->assertOk();
        $f['rate']->update(['rate_factor' => '70']);
        $before = $this->getJson($url)->assertOk()->assertJsonPath('credit.rate_factor', '80.0000')
            ->assertJsonPath('members.0.paid', '1440.00')->assertJsonPath('members.0.overdue', '0.00')
            ->assertJsonPath('members.1.paid', '0.00')->assertJsonPath('payments.1.allocations.0.payer_client_id', $f['clients'][1]->id)->json();
        $v = $this->reversalInput($f);
        Sanctum::actingAs($f['manager']);
        $this->postJson('/api/credit/receipts/'.$f['payment']['id'].'/reverse', $v)->assertOk();
        $after = $this->getJson($url)->assertOk()->assertJsonPath('members.0.paid', '940.00')
            ->assertJsonPath('members.0.balance', '10580.00')->assertJsonPath('members.0.overdue', '500.00')
            ->assertJsonPath('payments.0.reversed_on', now('America/Cancun')->toDateString())
            ->assertJsonPath('payments.1.reversed_on', null)->assertJsonCount(2, 'payments')->json();
        $this->assertSame($before['payments'][1]['allocations'], $after['payments'][1]['allocations']);
        $this->assertDatabaseCount('credit_payments', 2);
        $this->assertDatabaseCount('credit_payment_reversals', 1);
    }

    public function test_statement_access_is_scoped_and_omits_private_collection_and_reversal_fields(): void
    {
        $f = $this->collectionFixture();
        $this->postJson($f['base'].'/payments', $f['payment'])->assertOk();
        $url = $f['base'].'/statement';
        Sanctum::actingAs($f['user']);
        $r = $this->getJson($url)->assertOk()->assertJsonMissingPath('payments.0.bank_reference')
            ->assertJsonMissingPath('payments.0.bank_name')->assertJsonMissingPath('payments.0.notes')
            ->assertJsonMissingPath('payments.0.actor_name')->assertJsonMissingPath('members.0.curp');
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $other = User::factory()->create(['branch_id' => $f['branch']->id, 'is_active' => true]);
        $other->assignRole('Promotor');
        Sanctum::actingAs($other);
        $this->getJson($url)->assertNotFound();
        $other->syncRoles(['Gerente']);
        $branch = Branch::create(['name' => 'Otra sucursal', 'is_active' => true]);
        $other->forceFill(['branch_id' => $branch->id])->save();
        $this->getJson($url)->assertNotFound();
        $other->syncRoles([]);
        $this->getJson($url)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->getJson($url)->assertUnauthorized();
    }

    public function test_statement_includes_all_receipts_and_a_paid_loan_has_zero_balance(): void
    {
        $f = $this->collectionFixture('cash_branch');
        $p = $f['payment'];
        for ($i = 0; $i < 21; $i++) {
            $p['id'] = (string) Str::uuid();
            $p['amount'] = '1';
            $p['contributions'][0]['amount'] = '1';
            $state = $this->postJson($f['base'].'/payments', $p)->assertOk()->json();
            $p['balance_version'] = $state['balance_version'];
        }
        $statement = $this->getJson($f['base'].'/statement')->assertOk()->assertJsonCount(21, 'payments')
            ->assertJsonPath('members.0.paid', '21.00')->json();
        $sum = 0;
        $p['contributions'] = [];
        foreach ($statement['members'] as $m) {
            $sum += CreditCalculator::scaled($m['balance']);
            $p['contributions'][] = ['client_id' => $m['client_id'], 'payer_client_id' => $m['client_id'], 'amount' => $m['balance']];
        }
        $p['id'] = (string) Str::uuid();
        $p['amount'] = CreditCalculator::money($sum);
        $this->postJson($f['base'].'/payments', $p)->assertOk();
        $this->getJson($f['base'].'/statement')->assertOk()->assertJsonPath('credit.status', 'pagado')
            ->assertJsonPath('members.0.balance', '0.00')->assertJsonPath('members.1.balance', '0.00')
            ->assertJsonPath('members.2.balance', '0.00')->assertJsonCount(22, 'payments');
        $this->assertDatabaseCount('credit_payments', 22);
    }

    public function test_statement_refuses_undelivered_or_incomplete_records(): void
    {
        $f = $this->deliveryFixture();
        $this->getJson($f['base'].'/statement')->assertConflict();
        $this->postJson($f['base'].'/disbursement', $f['delivery'])->assertOk();
        DB::table('loan_installments')->where('loan_application_id', $f['loan']['id'])->where('number', 16)->delete();
        $this->getJson($f['base'].'/statement')->assertConflict();
    }

    public function test_collection_partial_solidarity_advance_and_exact_retry(): void
    {
        $f = $this->collectionFixture();
        $url = $f['base'].'/payments';
        $r = $this->postJson($url, $f['payment'])->assertOk()->assertJsonPath('payments.data.0.amount', '500.00')->assertJsonPath('payments.data.0.allocations.0.number', 1)->json();
        $this->postJson($url, $f['payment'])->assertOk();
        $this->assertDatabaseCount('credit_payments', 1);
        $this->assertDatabaseHas('loan_installments', ['client_id' => $f['clients'][0]->id, 'number' => 1, 'amount_due' => 720, 'amount_paid' => 500]);
        $p = $f['payment'];
        $p['amount'] = '600';
        $this->postJson($url, $p)->assertConflict();
        $p = $f['payment'];
        $p['id'] = (string) Str::uuid();
        $p['bank_reference'] = 'FOLIO-002';
        $this->postJson($url, $p)->assertConflict(); // stale balance
        $p['balance_version'] = $r['balance_version'];
        $p['amount'] = '940';
        $p['contributions'] = [['client_id' => $f['clients'][0]->id, 'payer_client_id' => $f['clients'][1]->id, 'amount' => '940']];
        $this->postJson($url, $p)->assertOk()->assertJsonCount(2, 'payments.data.0.allocations');
        $this->assertDatabaseHas('loan_installments', ['client_id' => $f['clients'][0]->id, 'number' => 1, 'amount_paid' => 720]);
        $this->assertDatabaseHas('loan_installments', ['client_id' => $f['clients'][0]->id, 'number' => 2, 'amount_paid' => 720]);
        $this->assertDatabaseHas('credit_payment_allocations', ['credit_payment_id' => $p['id'], 'payer_client_id' => $f['clients'][1]->id, 'amount' => 220]);
    }

    public function test_collection_rejects_duplicate_deposits_invalid_totals_and_overpayment_atomically(): void
    {
        $f = $this->collectionFixture();
        $url = $f['base'].'/payments';
        foreach (['amount' => '501', 'confirmed' => false, 'method' => 'cash_branch', 'bank_reference' => '', 'deposited_on' => now('America/Cancun')->addDay()->toDateString()] as $key => $value) {
            $p = $f['payment'];
            $p[$key] = $value;
            $this->postJson($url, $p)->assertUnprocessable();
        }
        $p = $f['payment'];
        $p['contributions'][0]['payer_client_id'] = (string) Str::uuid();
        $this->postJson($url, $p)->assertUnprocessable();
        $p = $f['payment'];
        $p['amount'] = '12000';
        $p['contributions'][0]['amount'] = '12000';
        $this->postJson($url, $p)->assertUnprocessable();
        $p = $f['payment'];
        $p['contributions'][] = ['client_id' => $f['clients'][1]->id, 'payer_client_id' => $f['clients'][1]->id, 'amount' => '99999'];
        $p['amount'] = '100499';
        $this->postJson($url, $p)->assertUnprocessable();
        $this->assertDatabaseCount('credit_payments', 0);
        $this->assertEquals(0, DB::table('loan_installments')->sum('amount_paid'));
        $state = $this->postJson($url, $f['payment'])->assertOk()->json();
        $p = $f['payment'];
        $p['id'] = (string) Str::uuid();
        $p['balance_version'] = $state['balance_version'];
        $p['bank_name'] = ' banco demo ';
        $p['bank_reference'] = 'folio-001';
        $this->postJson($url, $p)->assertConflict();
        $this->assertDatabaseCount('credit_payments', 1);
    }

    public function test_collection_cash_permissions_branch_and_full_settlement(): void
    {
        $f = $this->collectionFixture('cash_branch');
        $url = $f['base'].'/payments';
        Sanctum::actingAs($f['user']);
        $this->postJson($url, $f['payment'])->assertForbidden();
        $this->getJson($url)->assertForbidden();
        Sanctum::actingAs($f['manager']);
        $this->postJson($url, $f['payment'])->assertForbidden();
        Sanctum::actingAs($f['cashier']);
        $other = Branch::create(['name' => 'Otra', 'is_active' => true]);
        $f['cashier']->forceFill(['branch_id' => $other->id])->save();
        $this->getJson($url)->assertNotFound();
        $this->postJson($url, $f['payment'])->assertNotFound();
        $f['cashier']->forceFill(['branch_id' => $f['branch']->id])->save();
        $this->getJson('/api/intake/teams')->assertForbidden();
        $p = $f['payment'];
        $p['bank_name'] = 'No corresponde';
        $this->postJson($url, $p)->assertUnprocessable();
        $p = $f['payment'];
        $p['amount'] = '28160';
        $p['contributions'] = array_map(fn ($c, $amount) => ['client_id' => $c->id, 'payer_client_id' => $c->id, 'amount' => $amount], $f['clients'], ['11520', '10240', '6400']);
        $this->postJson($url, $p)->assertOk()->assertJsonPath('status', 'pagado')->assertJsonPath('members.0.balance', '0.00')->assertJsonPath('payments.data.0.method', 'cash_branch');
        $this->assertDatabaseCount('credit_payment_allocations', 48);
        $this->assertDatabaseHas('loan_applications', ['id' => $f['loan']['id'], 'status' => 'pagado', 'total_approved' => 22000]);
        $this->postJson($url, $p)->assertOk();
        $p['id'] = (string) Str::uuid();
        $this->postJson($url, $p)->assertConflict();
        $this->assertDatabaseCount('credit_payments', 1);
    }

    public function test_collection_live_arrears_and_partial_payment_reduce_overdue_balance(): void
    {
        $f = $this->collectionFixture('cash_branch');
        $url = $f['base'].'/payments';
        $due = CarbonImmutable::parse($f['delivery']['first_due_on'], 'America/Cancun');
        $this->travelTo($due->addDays(8)->setTime(12, 0));
        $this->renewStaffFixturePasswords($f);
        $state = $this->getJson($url)->assertOk()->assertJsonPath('members.0.overdue', '1440.00')->assertJsonPath('members.0.days_overdue', 8)->json();
        $p = $f['payment'];
        $p['balance_version'] = $state['balance_version'];
        $this->postJson($url, $p)->assertOk()->assertJsonPath('members.0.overdue', '940.00')->assertJsonPath('members.0.days_overdue', 8);
        $this->travelBack();
    }

    public function test_collection_rolls_back_payment_if_allocation_insert_fails(): void
    {
        $f = $this->collectionFixture('cash_branch');
        Log::shouldReceive('error')->once();
        $this->failWrite('credit_payment_allocations', 'insert');
        $this->postJson($f['base'].'/payments', $f['payment'])->assertStatus(500);
        $this->assertDatabaseCount('credit_payments', 0);
        $this->assertDatabaseCount('credit_payment_allocations', 0);
        $this->assertEquals(0, DB::table('loan_installments')->sum('amount_paid'));
        $this->assertDatabaseHas('loan_applications', ['id' => $f['loan']['id'], 'status' => 'entregado']);
    }

    public function test_payment_receipt_is_stable_signed_private_and_verifiable_without_login(): void
    {
        $f = $this->collectionFixture();
        $this->postJson($f['base'].'/payments', $f['payment'])->assertOk();
        $endpoint = '/api/credit/receipts/'.$f['payment']['id'];
        $receipt = $this->getJson($endpoint)->assertOk()->assertJsonPath('amount', '500.00')->assertJsonPath('bank_reference', 'FOLIO-001')->assertJsonCount(1, 'allocations')->json();
        $this->assertStringStartsWith('REC-CRE-', $receipt['folio']);
        $this->getJson($endpoint)->assertOk()->assertJsonPath('folio', $receipt['folio'])->assertJsonPath('verification_url', $receipt['verification_url']);
        $this->app['auth']->forgetGuards();
        $this->get($receipt['verification_url'])->assertOk()->assertSee('Pago registrado')->assertSee('500.00')->assertSee($receipt['folio'])->assertDontSee('FOLIO-001')->assertDontSee($f['clients'][0]->first_name)->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertDatabaseCount('credit_payments', 1);
        $this->assertEquals(500, DB::table('loan_installments')->sum('amount_paid'));
    }

    public function test_receipt_rejects_tampering_unknown_ids_and_unscoped_private_access(): void
    {
        $f = $this->collectionFixture();
        $this->postJson($f['base'].'/payments', $f['payment'])->assertOk();
        $endpoint = '/api/credit/receipts/'.$f['payment']['id'];
        $receipt = $this->getJson($endpoint)->assertOk()->json();
        $this->get($receipt['verification_url'].'&amount=999')->assertNotFound()->assertDontSee('Pago registrado');
        $this->get('/comprobantes/'.$f['payment']['id'].'/verificar')->assertNotFound();
        $this->get(str_replace($f['payment']['id'], (string) Str::uuid(), $receipt['verification_url']))->assertNotFound();
        $this->get(URL::signedRoute('receipts.verify', ['id' => (string) Str::uuid()]))->assertNotFound();
        Sanctum::actingAs($f['user']);
        $this->getJson($endpoint)->assertForbidden();
        Sanctum::actingAs($f['cashier']);
        $branch = Branch::create(['name' => 'Otra', 'is_active' => true]);
        $f['cashier']->forceFill(['branch_id' => $branch->id])->save();
        $this->getJson($endpoint)->assertNotFound();
    }

    private function cashClosingFixture(): array
    {
        $f = $this->collectionFixture('cash_branch');
        $this->postJson($f['base'].'/payments', $f['payment'])->assertOk();
        // Additional ledger fixture isolates cut aggregation from repayment-method authorization.
        $bank = (array) DB::table('credit_payments')->where('id', $f['payment']['id'])->first();
        $bank['id'] = (string) Str::uuid();
        $bank['number'] = 2;
        $bank['method'] = 'bank_deposit';
        $bank['amount'] = '1200.25';
        $bank['bank_name'] = 'Banco Demo';
        $bank['bank_reference'] = 'BANK-CUT-001';
        $bank['deposited_on'] = now('America/Cancun')->subDay()->toDateString();
        DB::table('credit_payments')->insert($bank);
        $previewUrl = '/api/cash/preview?branch_id='.$f['branch']->id.'&business_date='.now('America/Cancun')->toDateString();
        $preview = $this->getJson($previewUrl)->assertOk()->assertJsonPath('cash_total', '500.00')->assertJsonPath('bank_total', '1200.25')->assertJsonPath('payment_count', 2)->json();
        $closing = ['id' => (string) Str::uuid(), 'branch_id' => $f['branch']->id, 'business_date' => now('America/Cancun')->toDateString(), 'version' => $preview['version'], 'cash_counted' => '475.00', 'notes' => 'Faltante de 25 pendiente de revisión.', 'confirmed' => true];

        return $f + compact('previewUrl', 'closing', 'bank');
    }

    public function test_cash_closing_separates_channels_freezes_difference_and_retries_idempotently(): void
    {
        $this->freezeTime();
        $f = $this->cashClosingFixture();
        $before = DB::table('loan_installments')->sum('amount_paid');
        $saved = $this->postJson('/api/cash/closings', $f['closing'])->assertOk()->assertJsonPath('record.cash_total', '500.00')->assertJsonPath('record.bank_total', '1200.25')->assertJsonPath('record.cash_counted', '475.00')->assertJsonPath('record.cash_difference', '-25.00')->assertJsonPath('record.payment_count', 2)->assertJsonMissingPath('record.request_hash')->json();
        $this->assertStringStartsWith('COR-', $saved['record']['folio']);
        $this->assertSame(now('America/Cancun')->format('Y-m-d H:i:s'), $saved['record']['closed_at_local']);
        $this->postJson('/api/cash/closings', $f['closing'])->assertOk()->assertJsonPath('record.folio', $saved['record']['folio']);
        $this->getJson('/api/cash/closings/'.$f['closing']['id'])->assertOk()->assertJsonCount(2, 'payments.data');
        $this->getJson($f['previewUrl'])->assertOk()->assertJsonPath('payment_count', 0);
        $this->assertDatabaseCount('cash_closings', 1);
        $this->assertDatabaseCount('cash_closing_payments', 2);
        $this->assertEquals($before, DB::table('loan_installments')->sum('amount_paid'));
        $v = $f['closing'];
        $v['cash_counted'] = '500';
        $this->postJson('/api/cash/closings', $v)->assertConflict();
        $v = $f['closing'];
        $v['id'] = (string) Str::uuid();
        $this->postJson('/api/cash/closings', $v)->assertConflict();
    }

    public function test_printable_cut_contains_all_payments_and_keeps_original_totals_after_reversal(): void
    {
        $this->freezeTime();
        $f = $this->cashClosingFixture();
        for ($i = 3; $i <= 35; $i++) {
            $bank = $f['bank'];
            $bank['id'] = (string) Str::uuid();
            $bank['number'] = $i;
            $bank['bank_reference'] = 'BANK-CUT-'.$i;
            DB::table('credit_payments')->insert($bank);
        }
        $f['closing']['version'] = $this->getJson($f['previewUrl'])->assertOk()->json('version');
        $this->postJson('/api/cash/closings', $f['closing'])->assertOk();
        $before = DB::table('cash_closings')->first();
        $v = $this->reversalInput($f);
        Sanctum::actingAs($f['manager']);
        $this->postJson('/api/credit/receipts/'.$f['payment']['id'].'/reverse', $v)->assertOk();
        $url = '/api/cash/closings/'.$f['closing']['id'].'/report?page=999';
        $result = $this->getJson($url)->assertOk()->assertJsonCount(35, 'payments')
            ->assertJsonPath('record.cash_total', '500.00')
            ->assertJsonPath('record.bank_total', '40808.50')
            ->assertJsonPath('record.cash_counted', '475.00')
            ->assertJsonPath('record.cash_difference', '-25.00')
            ->assertJsonPath('adjustment_totals.cash_total', '500.00')
            ->assertJsonPath('adjustment_totals.bank_total', '0.00')
            ->assertJsonPath('adjustment_totals.count', 1)
            ->assertJsonPath('adjustments.0.credit_payment_id', $f['payment']['id'])
            ->assertJsonPath('generated_at_local', now('America/Cancun')->format('Y-m-d H:i:s'))
            ->assertJsonMissingPath('record.request_hash');
        $this->assertStringContainsString('no-store', $result->headers->get('Cache-Control'));
        $this->assertCount(35, array_unique(array_column($result->json('payments'), 'id')));
        $this->assertContains('BANK-CUT-35', array_column($result->json('payments'), 'bank_reference'));
        $this->getJson($url)->assertOk()->assertJsonPath('record.folio', $before->folio);
        $this->assertEquals($before, DB::table('cash_closings')->first());
        $this->assertDatabaseCount('cash_closings', 1);
        $this->assertDatabaseCount('credit_payments', 35);
    }

    public function test_printable_cut_requires_scope_and_never_exposes_other_cashiers_or_branches(): void
    {
        $f = $this->cashClosingFixture();
        $this->postJson('/api/cash/closings', $f['closing'])->assertOk();
        $url = '/api/cash/closings/'.$f['closing']['id'].'/report';
        $this->getJson($url)->assertOk()->assertJsonPath('adjustment_totals.count', 0);
        $other = User::factory()->create(['branch_id' => $f['branch']->id, 'is_active' => true]);
        $other->assignRole('Caja');
        Sanctum::actingAs($other);
        $this->getJson($url)->assertNotFound();
        Sanctum::actingAs($f['user']);
        $this->getJson($url)->assertForbidden();
        Sanctum::actingAs($f['manager']);
        $this->getJson($url)->assertOk();
        $branch = Branch::create(['name' => 'Otra sucursal', 'is_active' => true]);
        $f['manager']->forceFill(['branch_id' => $branch->id])->save();
        $this->getJson($url)->assertNotFound();
        $f['cashier']->forceFill(['is_active' => false])->save();
        Sanctum::actingAs($f['cashier']);
        $this->getJson($url)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->getJson($url)->assertUnauthorized();
    }

    public function test_printable_cut_refuses_an_incomplete_detail_instead_of_printing_wrong_totals(): void
    {
        $f = $this->cashClosingFixture();
        $this->postJson('/api/cash/closings', $f['closing'])->assertOk();
        DB::table('cash_closing_payments')->where('credit_payment_id', $f['bank']['id'])->delete();
        $this->getJson('/api/cash/closings/'.$f['closing']['id'].'/report')->assertConflict();
    }

    public function test_closing_rejects_stale_preview_invalid_difference_and_supplied_totals(): void
    {
        $f = $this->cashClosingFixture();
        foreach (['notes' => '', 'confirmed' => false, 'cash_counted' => '-1', 'cash_total' => '0', 'actor_id' => $f['manager']->id, 'business_date' => now('America/Cancun')->addDay()->toDateString()] as $key => $value) {
            $v = $f['closing'];
            $v[$key] = $value;
            $this->postJson('/api/cash/closings', $v)->assertUnprocessable();
        }
        $row = $f['bank'];
        $row['id'] = (string) Str::uuid();
        $row['number'] = 3;
        $row['bank_reference'] = 'BANK-CUT-002';
        DB::table('credit_payments')->insert($row);
        $this->postJson('/api/cash/closings', $f['closing'])->assertConflict();
        $this->assertDatabaseCount('cash_closings', 0);
        $this->assertDatabaseCount('cash_closing_payments', 0);
        $this->getJson($f['previewUrl'])->assertOk()->assertJsonPath('bank_total', '2400.50');
    }

    public function test_cash_closings_scope_cashiers_managers_and_other_branches(): void
    {
        $f = $this->cashClosingFixture();
        $this->postJson('/api/cash/closings', $f['closing'])->assertOk();
        $url = '/api/cash/closings/'.$f['closing']['id'];
        Sanctum::actingAs($f['user']);
        $this->getJson('/api/cash/options')->assertForbidden();
        $this->getJson($url)->assertForbidden();
        $otherCashier = User::factory()->create(['branch_id' => $f['branch']->id, 'is_active' => true]);
        $otherCashier->assignRole('Caja');
        Sanctum::actingAs($otherCashier);
        $this->getJson($url)->assertNotFound();
        $this->getJson('/api/cash/closings')->assertOk()->assertJsonPath('total', 0);
        $this->getJson($f['previewUrl'])->assertOk()->assertJsonPath('payment_count', 0);
        $this->postJson('/api/cash/closings', $f['closing'])->assertConflict();
        Sanctum::actingAs($f['manager']);
        $this->getJson($url)->assertOk();
        $this->getJson('/api/cash/closings')->assertOk()->assertJsonPath('total', 1);
        $this->getJson($f['previewUrl'])->assertForbidden();
        $this->postJson('/api/cash/closings', $f['closing'])->assertForbidden();
        $branch = Branch::create(['name' => 'Sucursal diferente', 'is_active' => true]);
        $f['manager']->forceFill(['branch_id' => $branch->id])->save();
        $this->getJson($url)->assertNotFound();
        $this->getJson('/api/cash/closings')->assertOk()->assertJsonPath('total', 0);
        Sanctum::actingAs($f['cashier']);
        $v = $f['closing'];
        $v['branch_id'] = $branch->id;
        $this->postJson('/api/cash/closings', $v)->assertNotFound();
        $this->getJson('/api/intake/teams')->assertForbidden();
    }

    public function test_later_payments_get_additional_cut_and_never_change_the_previous_cut(): void
    {
        $f = $this->cashClosingFixture();
        $this->postJson('/api/cash/closings', $f['closing'])->assertOk();
        $state = $this->getJson($f['base'].'/payments')->assertOk()->json();
        $p = $f['payment'];
        $p['id'] = (string) Str::uuid();
        $p['balance_version'] = $state['balance_version'];
        $this->postJson($f['base'].'/payments', $p)->assertOk();
        $next = $this->getJson($f['previewUrl'])->assertOk()->assertJsonPath('payment_count', 1)->assertJsonPath('cash_total', '500.00')->assertJsonPath('bank_total', '0.00')->json();
        $v = $f['closing'];
        $v['id'] = (string) Str::uuid();
        $v['version'] = $next['version'];
        $v['cash_counted'] = '500';
        $v['notes'] = null;
        $this->postJson('/api/cash/closings', $v)->assertOk()->assertJsonPath('record.cash_difference', '0.00');
        $this->getJson('/api/cash/closings/'.$f['closing']['id'])->assertOk()->assertJsonPath('record.payment_count', 2)->assertJsonPath('record.cash_difference', '-25.00');
        $this->assertDatabaseCount('cash_closing_payments', 3);
        $this->assertDatabaseCount('cash_closings', 2);
    }

    public function test_cash_closing_rolls_back_when_payment_link_fails(): void
    {
        $f = $this->cashClosingFixture();
        Log::shouldReceive('error')->once();
        $this->failWrite('cash_closing_payments', 'insert');
        $this->postJson('/api/cash/closings', $f['closing'])->assertStatus(500);
        $this->assertDatabaseCount('cash_closings', 0);
        $this->assertDatabaseCount('cash_closing_payments', 0);
        $this->getJson($f['previewUrl'])->assertOk()->assertJsonPath('payment_count', 2);
    }

    public function test_dashboard_scopes_real_metrics_and_cashiers_own_receipts(): void
    {
        $f = $this->collectionFixture('cash_branch');
        $this->postJson($f['base'].'/payments', $f['payment'])->assertOk();
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('groups', null)->assertJsonPath('active_credits', 1)->assertJsonPath('in_review', 0)->assertJsonPath('payments.total', '500.00')->assertJsonPath('payments.cash', '500.00')->assertJsonPath('payments.bank', '0.00')->assertJsonPath('payments.count', 1)->assertJsonPath('payments.scope', 'Tus cobros')->assertJsonPath('inbox.0.count', 1);
        Sanctum::actingAs($f['manager']);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('groups', 1)->assertJsonPath('active_credits', 1)->assertJsonPath('payments.total', '500.00')->assertJsonPath('payments.scope', 'Tu sucursal');
        $other = User::factory()->create(['branch_id' => $f['branch']->id, 'is_active' => true]);
        $other->assignRole('Caja');
        Sanctum::actingAs($other);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('payments.total', '0.00')->assertJsonPath('payments.count', 0)->assertJsonCount(0, 'inbox');
        Sanctum::actingAs($f['user']);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('groups', 1)->assertJsonPath('payments', null);
        $branch = Branch::create(['name' => 'Aislada', 'is_active' => true]);
        $f['manager']->forceFill(['branch_id' => $branch->id])->save();
        Sanctum::actingAs($f['manager']);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('groups', 0)->assertJsonPath('active_credits', 0)->assertJsonPath('payments.total', '0.00');
        $f['manager']->forceFill(['is_active' => false])->save();
        $this->getJson('/api/dashboard')->assertForbidden();
    }

    public function test_dashboard_uses_cancun_day_and_does_not_double_count_closed_payments(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 01:00:00', 'UTC')); // October 14 in Cancun.
        $f = $this->cashClosingFixture();
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('business_date', '2026-10-14')->assertJsonPath('payments.total', '1700.25')->assertJsonPath('payments.count', 2)->assertJsonPath('inbox.0.count', 2);
        $this->postJson('/api/cash/closings', $f['closing'])->assertOk();
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('payments.total', '1700.25')->assertJsonCount(0, 'inbox');
        $this->travelTo(CarbonImmutable::parse('2026-10-15 05:01:00', 'UTC'));
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('business_date', '2026-10-15')->assertJsonPath('payments.total', '0.00');
        $this->travelBack();
    }

    public function test_dashboard_inbox_reflects_review_status_and_permissions(): void
    {
        $f = $this->reviewFixture();
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('in_review', 1)->assertJsonPath('active_credits', 0)->assertJsonPath('inbox.0.to', '/gestion?status=pending')->assertJsonPath('inbox.0.count', 1)->assertJsonPath('payments', null);
        DB::table('loan_applications')->where('id', $f['loan']['id'])->update(['review_status' => 'returned']);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('in_review', 0)->assertJsonCount(0, 'inbox');
        Sanctum::actingAs($f['manager']);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('inbox.0.to', '/solicitudes-credito?status=returned');
        $f['team']->update(['is_active' => false]);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('groups', 0);
    }

    private function reversalInput(array $f): array
    {
        $receipt = $this->getJson('/api/credit/receipts/'.$f['payment']['id'])->assertOk()->json();

        return ['id' => (string) Str::uuid(), 'balance_version' => $receipt['balance_version'], 'reason' => 'Pago capturado por error; comprobación con expediente.', 'confirmed' => true];
    }

    public function test_reversal_restores_balance_preserves_receipt_and_releases_bank_reference(): void
    {
        $f = $this->collectionFixture();
        $this->postJson($f['base'].'/payments', $f['payment'])->assertOk();
        $v = $this->reversalInput($f);
        $url = '/api/credit/receipts/'.$f['payment']['id'].'/reverse';
        Sanctum::actingAs($f['manager']);
        $receipt = $this->postJson($url, $v)->assertOk()->assertJsonPath('status', 'reversed')->assertJsonPath('amount', '500.00')->assertJsonPath('reversal.actor_name', $f['manager']->name)->json();
        $this->postJson($url, $v)->assertOk();
        $this->assertDatabaseCount('credit_payment_reversals', 1);
        $this->assertDatabaseCount('credit_payments', 1);
        $this->assertDatabaseCount('credit_payment_allocations', 1);
        $this->assertDatabaseCount('active_bank_payment_references', 0);
        $this->assertEquals(0, DB::table('loan_installments')->sum('amount_paid'));
        $this->get($receipt['verification_url'])->assertOk()->assertSee('Pago revertido')->assertDontSee($v['reason'])->assertDontSee($f['manager']->name);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('payments.total', '0.00');
        $different = $v;
        $different['id'] = (string) Str::uuid();
        $this->postJson($url, $different)->assertConflict();
        $different = $v;
        $different['reason'] = 'Otro motivo que no coincide.';
        $this->postJson($url, $different)->assertConflict();
        Sanctum::actingAs($f['cashier']);
        $this->getJson('/api/cash/preview?branch_id='.$f['branch']->id.'&business_date='.now('America/Cancun')->toDateString())->assertOk()->assertJsonPath('payment_count', 0);
        $state = $this->getJson($f['base'].'/payments')->assertOk()->assertJsonPath('payments.data.0.reversal.id', $v['id'])->json();
        $p = $f['payment'];
        $p['id'] = (string) Str::uuid();
        $p['balance_version'] = $state['balance_version'];
        $this->postJson($f['base'].'/payments', $p)->assertOk();
        $this->assertDatabaseCount('credit_payments', 2);
        $this->assertDatabaseCount('active_bank_payment_references', 1);
    }

    public function test_reversing_closed_payment_keeps_original_cut_and_lists_adjustment(): void
    {
        $f = $this->cashClosingFixture();
        $this->postJson('/api/cash/closings', $f['closing'])->assertOk();
        $v = $this->reversalInput($f);
        Sanctum::actingAs($f['manager']);
        $this->postJson('/api/credit/receipts/'.$f['payment']['id'].'/reverse', $v)->assertOk();
        $this->getJson('/api/cash/closings/'.$f['closing']['id'])->assertOk()->assertJsonPath('record.cash_total', '500.00')->assertJsonPath('record.bank_total', '1200.25')->assertJsonPath('record.cash_counted', '475.00')->assertJsonPath('record.payment_count', 2)->assertJsonPath('adjustments.total', 1)->assertJsonPath('adjustments.data.0.id', $v['id']);
        $this->assertDatabaseCount('cash_closing_payments', 2);
        $this->assertDatabaseCount('cash_closings', 1);
    }

    public function test_reversal_enforces_permissions_scope_reason_and_current_balance(): void
    {
        $f = $this->collectionFixture('cash_branch');
        $this->postJson($f['base'].'/payments', $f['payment'])->assertOk();
        $v = $this->reversalInput($f);
        $url = '/api/credit/receipts/'.$f['payment']['id'].'/reverse';
        $this->postJson($url, $v)->assertForbidden();
        Sanctum::actingAs($f['user']);
        $this->postJson($url, $v)->assertForbidden();
        Sanctum::actingAs($f['manager']);
        foreach (['reason' => '', 'confirmed' => false, 'amount' => '500'] as $key => $value) {
            $p = $v;
            $p[$key] = $value;
            $this->postJson($url, $p)->assertUnprocessable();
        }
        $p = $v;
        $p['balance_version'] = str_repeat('0', 64);
        $this->postJson($url, $p)->assertConflict();
        $branch = Branch::create(['name' => 'Otra', 'is_active' => true]);
        $f['manager']->forceFill(['branch_id' => $branch->id])->save();
        $this->postJson($url, $v)->assertNotFound();
        $this->assertDatabaseCount('credit_payment_reversals', 0);
        $this->assertEquals(500, DB::table('loan_installments')->sum('amount_paid'));
    }

    public function test_reversal_of_settlement_reopens_credit_without_changing_approved_terms(): void
    {
        $f = $this->collectionFixture('cash_branch');
        $p = $f['payment'];
        $p['amount'] = '28160';
        $p['contributions'] = array_map(fn ($c, $amount) => ['client_id' => $c->id, 'payer_client_id' => $c->id, 'amount' => $amount], $f['clients'], ['11520', '10240', '6400']);
        $this->postJson($f['base'].'/payments', $p)->assertOk()->assertJsonPath('status', 'pagado');
        $v = $this->reversalInput($f);
        Sanctum::actingAs($f['manager']);
        $this->postJson('/api/credit/receipts/'.$f['payment']['id'].'/reverse', $v)->assertOk();
        $this->assertDatabaseHas('loan_applications', ['id' => $f['loan']['id'], 'status' => 'entregado', 'total_approved' => 22000]);
        $this->assertEquals(0, DB::table('loan_installments')->sum('amount_paid'));
    }

    public function test_reversal_rolls_back_if_restoring_installments_fails(): void
    {
        $f = $this->collectionFixture();
        $this->postJson($f['base'].'/payments', $f['payment'])->assertOk();
        $v = $this->reversalInput($f);
        Sanctum::actingAs($f['manager']);
        Log::shouldReceive('error')->once();
        $this->failWrite('loan_installments', 'update');
        $this->postJson('/api/credit/receipts/'.$f['payment']['id'].'/reverse', $v)->assertStatus(500);
        $this->assertDatabaseCount('credit_payment_reversals', 0);
        $this->assertDatabaseCount('active_bank_payment_references', 1);
        $this->assertEquals(500, DB::table('loan_installments')->sum('amount_paid'));
    }

    public function test_reversal_does_not_reallocate_later_payments(): void
    {
        $f = $this->collectionFixture('cash_branch');
        $first = $this->postJson($f['base'].'/payments', $f['payment'])->assertOk()->json();
        $later = $f['payment'];
        $later['id'] = (string) Str::uuid();
        $later['balance_version'] = $first['balance_version'];
        $this->postJson($f['base'].'/payments', $later)->assertOk();
        $v = $this->reversalInput($f);
        Sanctum::actingAs($f['manager']);
        $this->postJson('/api/credit/receipts/'.$f['payment']['id'].'/reverse', $v)->assertOk();
        $this->assertDatabaseHas('loan_installments', ['client_id' => $f['clients'][0]->id, 'number' => 1, 'amount_paid' => 220]);
        $this->assertDatabaseHas('loan_installments', ['client_id' => $f['clients'][0]->id, 'number' => 2, 'amount_paid' => 280]);
        $this->assertDatabaseCount('credit_payment_allocations', 3);
        $this->assertEquals(500, DB::table('loan_installments')->sum('amount_paid'));
    }
}
