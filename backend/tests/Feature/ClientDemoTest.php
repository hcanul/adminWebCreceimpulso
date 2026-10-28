<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ClientDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_totals_schedule_and_signed_receipts(): void
    {
        $b = Branch::create(['name' => 'DEMO APP — Solo pruebas', 'is_active' => true]);
        $c = Client::create(['branch_id' => $b->id, 'curp' => 'DEMO00000000000000', 'first_name' => 'Demo', 'last_name' => 'Prueba', 'status' => 'activo']);
        DB::table('clients')->where('id', $c->id)->update(['folio' => 'CLI-S009-000001']);
        Sanctum::actingAs(ClientAccount::create(['client_id' => $c->id, 'password' => 'Prueba123456789', 'is_active' => true]), ['client:read']);
        $data = $this->getJson('/api/client/demo')->assertOk()->assertJsonPath('demo', true)->assertJsonCount(16, 'schedule')->json();
        $this->assertSame($data['loan']['total'], array_sum(array_column($data['schedule'], 'amount')));
        $this->assertSame($data['loan']['paid'], array_sum(array_column($data['receipts'], 'amount')));
        $this->assertSame($data['loan']['balance'], array_sum(array_column($data['schedule'], 'remaining')));
        $this->assertSame($data['loan']['late_fees'], array_sum(array_column($data['fees'], 'amount')));
        $this->assertSame($data['loan']['payable'], $data['loan']['overdue'] + $data['loan']['due_today'] + $data['loan']['late_fees']);
        $url = $data['receipts'][0]['verification_url'];
        $this->get($url)->assertOk()->assertSee('SIN VALIDEZ DE PAGO');
        $this->get(str_replace('DEMO-003', 'DEMO-001', $url))->assertForbidden();
        DB::table('clients')->where('id', $c->id)->update(['folio' => 'CLI-S009-000002']);
        app('auth')->user()->unsetRelation('client');
        $this->getJson('/api/client/demo')->assertOk()->assertExactJson(['demo' => false]);
    }
}
