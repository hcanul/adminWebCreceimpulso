<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientAccount;
use App\Models\LoanApplication;
use App\Models\Promoter;
use App\Models\Rate;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ClientContractAccessTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $b = Branch::create(['name' => 'Prueba', 'is_active' => true]);
        $u = User::factory()->create(['branch_id' => $b->id, 'is_active' => true]);
        $u->assignRole('Gerente');
        Promoter::create(['id' => $u->id, 'branch_id' => $b->id, 'is_active' => true]);
        $t = Team::create(['name' => 'Grupo', 'branch_id' => $b->id, 'promoter_id' => $u->id]);
        $rate = Rate::create(['name' => 'Cobre', 'cicle' => 1, 'min_amount' => 1000, 'max_amount' => 10000, 'rate_factor' => 80]);
        $c = Client::create(['branch_id' => $b->id, 'curp' => 'TEST900101HQRXXX01', 'first_name' => 'Cliente', 'last_name' => 'Prueba', 'status' => 'activo']);
        $l = LoanApplication::create(['branch_id' => $b->id, 'team_id' => $t->id, 'promoter_id' => $u->id, 'rate_id' => $rate->id]);
        $l->forceFill(['status' => 'aprobado', 'approved_at' => now(), 'authorization_version' => 1, 'approved_snapshot' => ['members' => [['client_id' => $c->id]]]])->save();
        $id = (string) Str::uuid();
        $html = '<html><body>Contrato</body></html>';
        DB::table('document_versions')->insert(['id' => $id, 'branch_id' => $b->id, 'source_type' => 'credit', 'source_id' => $l->id, 'kind' => 'contract_review', 'source_version' => 1, 'template_version' => 2, 'actor_id' => $u->id, 'actor_name' => $u->name, 'snapshot' => '{}', 'html' => $html, 'sha256' => hash('sha256', $html), 'created_at' => now()]);
        Sanctum::actingAs($u);

        return [$u, $c, $id];
    }

    public function test_print_issues_secret_once_without_persisting_or_resetting(): void
    {
        [$u,$c,$id] = $this->fixture();
        $this->getJson('/api/documents/versions/'.$id)->assertOk();
        $this->assertDatabaseCount('client_accounts', 0);
        $body = $this->postJson('/api/documents/versions/'.$id.'/print')->assertOk()->json('html');
        preg_match('/Ci[a-f0-9]{20}/', $body, $m);
        $this->assertNotEmpty($m);
        $pass = $m[0];
        $a = ClientAccount::where('client_id', $c->id)->firstOrFail();
        $this->assertTrue(Hash::check($pass, $a->password));
        $this->assertTrue($a->must_change_password);
        $this->assertStringNotContainsString($pass, DB::table('document_versions')->get()->toJson());
        $this->assertStringNotContainsString($pass, DB::table('access_audits')->get()->toJson());
        $this->postJson('/api/documents/versions/'.$id.'/print')->assertOk()->assertDontSee($pass);
        $this->assertSame($a->password, $a->fresh()->password);
        $this->assertDatabaseCount('client_accounts', 1);
        $other = User::factory()->create(['branch_id' => Branch::create(['name' => 'Otra', 'is_active' => true])->id, 'is_active' => true]);
        $other->assignRole('Gerente');
        Sanctum::actingAs($other);
        $this->postJson('/api/documents/versions/'.$id.'/print')->assertNotFound();
    }

    public function test_temporary_access_is_restricted_until_password_change_and_revokes_sessions(): void
    {
        [$u,$c,$id] = $this->fixture();
        $body = $this->postJson('/api/documents/versions/'.$id.'/print')->assertOk()->json('html');
        preg_match('/Ci[a-f0-9]{20}/', $body, $m);
        app('auth')->forgetGuards();
        $token = $this->postJson('/api/client/login', ['identifier' => $c->folio, 'password' => $m[0]])->assertOk()->assertJsonPath('must_change_password', true)->json('token');
        app('auth')->forgetGuards();
        $this->withToken($token)->getJson('/api/client/statement')->assertStatus(423);
        app('auth')->forgetGuards();
        $this->withToken($token)->getJson('/api/client/me')->assertOk()->assertJsonPath('must_change_password', true);
        app('auth')->forgetGuards();
        $this->withToken($token)->postJson('/api/client/password', ['current_password' => $m[0], 'password' => $m[0], 'password_confirmation' => $m[0]])->assertUnprocessable();
        app('auth')->forgetGuards();
        $this->withToken($token)->postJson('/api/client/password', ['current_password' => $m[0], 'password' => 'NuevaSegura123', 'password_confirmation' => 'NuevaSegura123'])->assertNoContent();
        app('auth')->forgetGuards();
        $this->withToken($token)->getJson('/api/client/me')->assertUnauthorized();
        app('auth')->forgetGuards();
        $this->postJson('/api/client/login', ['identifier' => $c->folio, 'password' => 'NuevaSegura123'])->assertOk()->assertJsonPath('must_change_password', false);
    }

    public function test_expired_temporary_password_cannot_login(): void
    {
        [$u,$c,$id] = $this->fixture();
        $body = $this->postJson('/api/documents/versions/'.$id.'/print')->assertOk()->json('html');
        preg_match('/Ci[a-f0-9]{20}/', $body, $m);
        $this->travel(7)->days();
        app('auth')->forgetGuards();
        $this->postJson('/api/client/login', ['identifier' => $c->folio, 'password' => $m[0]])->assertUnprocessable();
    }

    public function test_presencial_recovery_preserves_account_until_redemption_and_revokes_sessions(): void
    {
        [$staff,$client,$id] = $this->fixture();
        $account = ClientAccount::create(['client_id' => $client->id, 'password' => 'AnteriorSegura123', 'is_active' => true]);
        $oldHash = $account->password;
        $token = $account->createToken('test', ['client:read'])->plainTextToken;
        $url = '/api/client-access/clients/'.$client->id.'/recovery';
        $this->postJson($url, ['identity_verified' => false])->assertUnprocessable();
        $code = $this->postJson($url, ['identity_verified' => true])->assertCreated()->json('code');
        $this->assertSame($oldHash, $account->fresh()->password);
        $this->assertSame(1, $account->tokens()->count());
        app('auth')->forgetGuards();
        $this->withToken($token)->getJson('/api/client/me')->assertOk();
        $payload = ['code' => $code, 'password' => 'AnteriorSegura123', 'password_confirmation' => 'AnteriorSegura123'];
        $this->postJson('/api/client/activate', $payload)->assertUnprocessable();
        $payload['password'] = $payload['password_confirmation'] = 'ReemplazoSegura456';
        $this->postJson('/api/client/activate', $payload)->assertOk();
        $this->assertTrue(Hash::check('ReemplazoSegura456', $account->fresh()->password));
        $this->assertFalse($account->fresh()->must_change_password);
        $this->assertSame(0, $account->tokens()->count());
        $this->postJson('/api/client/activate', $payload)->assertUnprocessable();
        app('auth')->forgetGuards();
        $this->withToken($token)->getJson('/api/client/me')->assertUnauthorized();
        $this->assertDatabaseHas('access_audits', ['subject_id' => $client->id, 'action' => 'recovery_consumed']);
    }

    public function test_recovery_is_scoped_and_stale_or_expired_codes_cannot_change_password(): void
    {
        [$staff,$client,$id] = $this->fixture();
        $account = ClientAccount::create(['client_id' => $client->id, 'password' => 'AnteriorSegura123', 'is_active' => true, 'must_change_password' => true, 'temporary_password_expires_at' => now()->subDay()]);
        $url = '/api/client-access/clients/'.$client->id.'/recovery';
        $code = $this->postJson($url, ['identity_verified' => true])->assertCreated()->json('code');
        $account->update(['password' => 'CambioPosterior123']);
        $payload = ['code' => $code, 'password' => 'OtraClaveSegura456', 'password_confirmation' => 'OtraClaveSegura456'];
        $this->postJson('/api/client/activate', $payload)->assertUnprocessable();
        $code = $this->postJson($url, ['identity_verified' => true])->assertCreated()->json('code');
        $this->travel(30)->minutes();
        $payload['code'] = $code;
        $this->postJson('/api/client/activate', $payload)->assertUnprocessable();
        $other = User::factory()->create(['branch_id' => Branch::create(['name' => 'Otra', 'is_active' => true])->id, 'is_active' => true]);
        $other->assignRole('Gerente');
        Sanctum::actingAs($other);
        $this->postJson($url, ['identity_verified' => true])->assertNotFound();
        $promoter = User::factory()->create(['branch_id' => $client->branch_id, 'is_active' => true]);
        $promoter->assignRole('Promotor');
        Sanctum::actingAs($promoter);
        $this->postJson($url, ['identity_verified' => true])->assertForbidden();
        $this->assertTrue(Hash::check('CambioPosterior123', $account->fresh()->password));
    }

    public function test_print_rejects_old_authorization_without_creating_accounts(): void
    {
        [$staff,$client,$id] = $this->fixture();
        DB::table('document_versions')->where('id', $id)->update(['source_version' => 99]);
        $this->postJson('/api/documents/versions/'.$id.'/print')->assertConflict();
        $this->assertDatabaseCount('client_accounts',0);
    }
}
