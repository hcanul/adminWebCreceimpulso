<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClientSessionTest extends TestCase
{
    use RefreshDatabase;

    private function account(): ClientAccount
    {
        $branch = Branch::create(['name' => 'Carrillo', 'is_active' => true]);
        $client = Client::create(['branch_id' => $branch->id, 'curp' => 'TEST900101HQRXXX01', 'first_name' => 'María', 'last_name' => 'Prueba', 'status' => 'activo']);

        return ClientAccount::create(['client_id' => $client->id, 'password' => 'SeguraCliente123']);
    }

    private function forgetAuth(): void
    {
        app('auth')->forgetGuards();
    }

    public function test_real_client_token_restores_profile_and_is_revoked_on_logout(): void
    {
        $a = $this->account();
        $token = $this->postJson('/api/client/login', ['identifier' => $a->client->folio, 'password' => 'SeguraCliente123'])->assertOk()->json('token');
        $this->forgetAuth();
        $this->withToken($token)->getJson('/api/client/me')->assertOk()->assertJsonPath('folio', $a->client->folio)->assertJsonMissingPath('password')->assertJsonMissingPath('curp');
        $this->forgetAuth();
        $this->withToken($token)->getJson('/api/me')->assertForbidden();
        $this->forgetAuth();
        $this->withToken($token)->postJson('/api/client/logout')->assertNoContent();
        $this->forgetAuth();
        $this->withToken($token)->getJson('/api/client/me')->assertUnauthorized();
    }

    public function test_disabled_expired_and_wrong_scope_tokens_cannot_read_profile(): void
    {
        $a = $this->account();
        $token = $a->createToken('test', ['client:read'], now()->addHours(8))->plainTextToken;
        $a->update(['is_active' => false]);
        $this->withToken($token)->getJson('/api/client/me')->assertUnauthorized();
        $a->update(['is_active' => true]);
        $this->travel(9)->hours();
        $this->forgetAuth();
        $this->withToken($token)->getJson('/api/client/me')->assertUnauthorized();
        $token = $a->createToken('wrong', ['other'])->plainTextToken;
        $this->forgetAuth();
        $this->withToken($token)->getJson('/api/client/me')->assertForbidden();
        $staff = User::factory()->create(['is_active' => true]);
        $token = $staff->createToken('staff')->plainTextToken;
        $this->forgetAuth();
        $this->withToken($token)->getJson('/api/client/me')->assertForbidden();
    }

    public function test_login_errors_do_not_reveal_accounts(): void
    {
        $a = $this->account();
        $bad = $this->postJson('/api/client/login', ['identifier' => $a->client->folio, 'password' => 'incorrecta'])->assertUnprocessable()->json('errors');
        $unknown = $this->postJson('/api/client/login', ['identifier' => 'NO-EXISTE', 'password' => 'incorrecta'])->assertUnprocessable()->json('errors');
        $this->assertSame($bad, $unknown);
        $a->update(['is_active' => false]);
        $this->postJson('/api/client/login', ['identifier' => $a->client->folio, 'password' => 'SeguraCliente123'])->assertUnprocessable();
    }
}
