<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ClientActivationTest extends TestCase
{
    use RefreshDatabase;

    private function setupClient(): Client
    {
        $b = Branch::create(['name' => 'Carrillo', 'is_active' => true]);
        $u = User::factory()->create(['is_active' => true, 'branch_id' => $b->id]);
        $u->assignRole('Gerente');
        Sanctum::actingAs($u);

        return Client::create(['branch_id' => $b->id, 'curp' => 'TEST900101HQRXXX01', 'first_name' => 'Prueba', 'last_name' => 'Cliente', 'status' => 'activo']);
    }

    private function issue(Client $c): string
    {
        return $this->postJson('/api/client-access/clients/'.$c->id.'/activation', ['identity_verified' => true])->assertCreated()->json('code');
    }

    private function activate(string $code)
    {
        return $this->postJson('/api/client/activate', ['code' => $code, 'password' => 'SeguraCliente123', 'password_confirmation' => 'SeguraCliente123']);
    }

    public function test_activation_is_single_use_hashed_and_audited(): void
    {
        $c = $this->setupClient();
        $this->postJson('/api/client-access/search', ['identifier' => $c->folio])->assertOk()->assertJsonPath('clients.0.id', $c->id)->assertJsonMissingPath('clients.0.curp');
        $this->postJson('/api/client-access/clients/'.$c->id.'/activation', [])->assertUnprocessable();
        $code = $this->issue($c);
        $this->assertDatabaseHas('client_activation_codes', ['code_hash' => hash('sha256', $code), 'consumed_at' => null]);
        $this->activate($code)->assertOk();
        $account = ClientAccount::where('client_id', $c->id)->firstOrFail();
        $this->assertTrue(Hash::check('SeguraCliente123', $account->password));
        $this->activate($code)->assertUnprocessable();
        $this->postJson('/api/client-access/clients/'.$c->id.'/activation', ['identity_verified' => true])->assertConflict();
        $this->assertDatabaseCount('client_accounts', 1);
        $this->assertDatabaseCount('access_audits', 2);
        $this->assertStringNotContainsString($code, DB::table('access_audits')->get()->toJson());
    }

    public function test_reissued_expired_and_invalid_codes_are_rejected(): void
    {
        $c = $this->setupClient();
        $old = $this->issue($c);
        $new = $this->issue($c);
        $this->activate($old)->assertUnprocessable();
        $this->activate(str_repeat('a', 32))->assertUnprocessable();
        $this->travel(30)->minutes();
        $this->activate($new)->assertUnprocessable();
        $this->assertDatabaseCount('client_accounts', 0);
    }

    public function test_branch_and_staff_boundaries_are_enforced(): void
    {
        $c = $this->setupClient();
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole('Gerente');
        Sanctum::actingAs($u);
        $this->postJson('/api/client-access/search', ['identifier' => $c->folio])->assertOk()->assertJsonCount(0, 'clients');
        $this->postJson('/api/client-access/clients/'.$c->id.'/activation', ['identity_verified' => true])->assertNotFound();
        $u->syncRoles('Promotor');
        $this->postJson('/api/client-access/search', ['identifier' => $c->folio])->assertForbidden();
        $account = ClientAccount::create(['client_id' => $c->id, 'password' => 'SeguraCliente123']);
        Sanctum::actingAs($account);
        foreach (['/api/me', '/api/dashboard', '/api/intake/options', '/api/administration/users', '/api/cash/options', '/api/credit/rates'] as $url) {
            $this->getJson($url)->assertForbidden();
        }
        $this->postJson('/api/client-access/search', ['identifier' => $c->folio])->assertForbidden();
    }

    public function test_inactive_client_cannot_activate(): void
    {
        $c = $this->setupClient();
        $code = $this->issue($c);
        $c->update(['status' => 'baja']);
        $this->activate($code)->assertUnprocessable();
        $this->assertDatabaseCount('client_accounts',0);
    }
}
