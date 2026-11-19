<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class BranchManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role = 'Super Admin'): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole($role);
        Sanctum::actingAs($u);

        return $u;
    }

    private function fields(): array
    {
        return ['name' => 'Nueva sucursal', 'city' => 'Carrillo', 'phone' => '0000000000', 'email' => 'sucursal@example.test', 'address' => 'Domicilio de prueba', 'is_active' => true];
    }

    public function test_creates_updates_and_retries_preserving_uuid_folio_and_audit(): void
    {
        $this->actor();
        $input = ['id' => (string) Str::uuid(), ...$this->fields()];
        $saved = $this->postJson('/api/branches', $input)->assertCreated()->assertJsonPath('management_version', 1)->assertJsonMissingPath('creation_hash')->json();
        $this->postJson('/api/branches', $input)->assertCreated()->assertJsonPath('folio_code', $saved['folio_code']);
        $this->assertDatabaseCount('branches', 1);
        $this->assertDatabaseCount('access_audits', 1);
        $edit = [...$this->fields(), 'name' => 'Sucursal actualizada', 'version' => 1];
        $this->putJson('/api/branches/'.$saved['id'], $edit)->assertOk()->assertJsonPath('management_version', 2)->assertJsonPath('folio_code', $saved['folio_code']);
        $this->putJson('/api/branches/'.$saved['id'], $edit)->assertOk();
        $this->assertDatabaseCount('access_audits', 2);
        $this->putJson('/api/branches/'.$saved['id'], [...$edit, 'name' => 'Otra edición'])->assertConflict();
        $this->putJson('/api/branches/'.$saved['id'], [...$edit, 'version' => 2, 'folio_code' => 'CAMBIO'])->assertUnprocessable();
        $this->getJson('/api/branches?search=actualizada')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/branches?search=%25')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_general_manager_can_manage_but_local_manager_and_cashier_cannot(): void
    {
        $this->actor('Gerente General');
        $input = ['id' => (string) Str::uuid(), ...$this->fields()];
        $this->postJson('/api/branches', $input)->assertCreated();
        foreach (['Gerente', 'Caja', 'Promotor'] as $role) {
            $this->actor($role);
            $this->getJson('/api/branches')->assertForbidden();
            $this->postJson('/api/branches', ['id' => (string) Str::uuid(), ...$this->fields()])->assertForbidden();
        }
    }

    public function test_deactivation_requires_no_active_users_and_preserves_history(): void
    {
        $this->actor();
        $branch = Branch::create(['name' => 'Sucursal', 'city' => null, 'is_active' => true]);
        $user = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $input = [...$this->fields(), 'name' => 'Sucursal', 'is_active' => false, 'version' => 0];
        $this->putJson('/api/branches/'.$branch->id, $input)->assertUnprocessable();
        $user->forceFill(['is_active' => false])->save();
        $this->putJson('/api/branches/'.$branch->id, $input)->assertOk()->assertJsonPath('is_active', false);
        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'is_active' => false, 'deleted_at' => null]);
        $this->getJson('/api/branches?status=active')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/branches?status=inactive')->assertOk()->assertJsonPath('total', 1);
        $this->putJson('/api/branches/'.$branch->id, [...$input, 'is_active' => true, 'version' => 1])->assertOk();
    }

    public function test_bad_fields_and_uuid_reuse_do_not_create_branches(): void
    {
        $this->actor();
        $input = ['id' => (string) Str::uuid(), ...$this->fields()];
        $this->postJson('/api/branches', [...$input, 'email' => 'no-es-correo'])->assertUnprocessable();
        $this->postJson('/api/branches', $input)->assertCreated();
        $this->postJson('/api/branches', [...$input, 'name' => 'Otro contenido'])->assertConflict();
        $this->assertDatabaseCount('branches',1);
    }
}
