<?php

namespace Tests\Feature;

use App\Domains\Documents\Services\DocumentProfiles;
use App\Models\Branch;
use App\Models\Promoter;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role = 'Super Admin'): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_document_profiles_support_general_branch_drafts_audit_and_conflicts(): void
    {
        $this->actor();
        $branch = Branch::create(['name' => 'Carrillo', 'is_active' => true]);
        $fields = array_fill_keys(DocumentProfiles::FIELDS, '');
        $this->getJson('/api/administration/document-profiles/'.$branch->id)->assertOk()->assertJsonPath('effective.source', 'general')->assertJsonCount(5, 'effective.missing');
        $fields['legal_address'] = 'Domicilio general';
        $data = ['version' => 0, 'use_general' => true, 'fields' => $fields];
        $this->putJson('/api/administration/document-profiles/general', $data)->assertOk()->assertJsonPath('profile.version', 1);
        $this->putJson('/api/administration/document-profiles/general', $data)->assertConflict();
        $this->getJson('/api/administration/document-profiles/'.$branch->id)->assertOk()->assertJsonPath('effective.fields.legal_address', 'Domicilio general');
        $data['use_general'] = false;
        $data['fields']['legal_address'] = 'Domicilio Carrillo';
        $this->putJson('/api/administration/document-profiles/'.$branch->id, $data)->assertOk()->assertJsonPath('effective.source', $branch->id)->assertJsonPath('effective.fields.legal_address', 'Domicilio Carrillo');
        $data['version'] = 1;
        $data['use_general'] = true;
        $this->putJson('/api/administration/document-profiles/'.$branch->id, $data)->assertOk()->assertJsonPath('effective.fields.legal_address', 'Domicilio general')->assertJsonPath('profile.fields.legal_address', 'Domicilio Carrillo');
        $this->assertDatabaseCount('access_audits', 3);
        $this->actor('Gerente General');
        $this->getJson('/api/administration/document-profiles/general')->assertForbidden();
        $this->putJson('/api/administration/document-profiles/general', $data)->assertForbidden();
    }

    private function payload(): array
    {
        $branch = Branch::create(['name' => 'Prueba', 'city' => 'Prueba', 'is_active' => true]);

        return ['name' => 'Promotora Prueba', 'email' => 'promotora@example.invalid', 'password' => 'SeguraPrueba123', 'password_confirmation' => 'SeguraPrueba123', 'roles' => ['Promotor'], 'branch_id' => $branch->id, 'is_active' => true];
    }

    public function test_management_is_exclusive_to_active_super_admin(): void
    {
        $this->actor('Gerente General');
        $this->getJson('/api/administration/users')->assertForbidden();
        $this->postJson('/api/administration/users', [])->assertForbidden();
        $this->postJson('/api/administration/roles', [])->assertForbidden();
        $u = $this->actor();
        $u->is_active = false;
        $u->save();
        $this->getJson('/api/administration/options')->assertForbidden();
    }

    public function test_creates_user_and_shared_uuid_promoter_with_hashed_password_and_audit(): void
    {
        $this->actor();
        $data = $this->payload();
        $result = $this->postJson('/api/administration/users', $data)->assertCreated()->assertJsonMissingPath('password');
        $id = $result->json('id');
        $this->assertTrue(Hash::check($data['password'], User::findOrFail($id)->password));
        $this->assertDatabaseHas('promoters', ['id' => $id, 'branch_id' => $data['branch_id'], 'is_active' => true]);
        $this->assertDatabaseHas('access_audits', ['subject_id' => $id, 'action' => 'created']);
        $this->assertStringNotContainsString($data['password'], DB::table('access_audits')->first()->changes);
        $this->postJson('/api/administration/users', $data)->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_requires_branch_and_strong_confirmed_password_and_known_roles(): void
    {
        $this->actor();
        $data = $this->payload();
        $this->postJson('/api/administration/users', array_replace($data, ['branch_id' => null]))->assertUnprocessable();
        $this->postJson('/api/administration/users', array_replace($data, ['password' => 'weak']))->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/administration/users', array_replace($data, ['roles' => ['Inventado']]))->assertUnprocessable()->assertJsonValidationErrors('roles.0');
    }

    public function test_protects_self_and_assigned_promoters_and_revokes_sessions_on_edit(): void
    {
        $actor = $this->actor();
        $data = $this->payload();
        $this->putJson('/api/administration/users/'.$actor->id, array_replace($data, ['email' => $actor->email, 'is_active' => false]))->assertUnprocessable();
        $id = $this->postJson('/api/administration/users', $data)->assertCreated()->json('id');
        $u = User::findOrFail($id);
        $u->createToken('test');
        unset($data['password'],$data['password_confirmation']);
        $data['name'] = 'Nombre corregido';
        $this->putJson('/api/administration/users/'.$id, $data)->assertOk();
        $this->assertSame(0, $u->tokens()->count());
        Team::create(['name' => 'Grupo', 'branch_id' => $data['branch_id'], 'promoter_id' => $id, 'created_by' => $actor->id]);
        $this->putJson('/api/administration/users/'.$id, array_replace($data, ['is_active' => false]))->assertUnprocessable();
        $this->assertTrue((bool) Promoter::findOrFail($id)->is_active);
    }

    public function test_role_permissions_actually_control_api_and_super_admin_is_protected(): void
    {
        $admin = $this->actor();
        $role = Role::findByName('Gerente', 'web');
        $this->putJson('/api/administration/roles/'.$role->id, ['permissions' => []])->assertOk();
        $this->actor('Gerente');
        $this->getJson('/api/intake/options')->assertForbidden();
        Sanctum::actingAs($admin);
        $this->putJson('/api/administration/roles/'.$role->id, ['permissions' => ['intake.access']])->assertOk();
        $this->actor('Gerente');
        $this->getJson('/api/intake/options')->assertOk();
        Sanctum::actingAs($admin);
        $this->putJson('/api/administration/roles/'.Role::findByName('Super Admin', 'web')->id, ['permissions' => []])->assertUnprocessable();
        $this->postJson('/api/administration/roles', ['name' => 'Auxiliar', 'permissions' => ['intake.submit']])->assertUnprocessable();
        $this->postJson('/api/administration/roles', ['name' => 'Auxiliar', 'permissions' => ['intake.access']])->assertCreated();
        $this->postJson('/api/administration/roles', ['name' => 'Tecnico', 'permissions' => ['roles.manage']])->assertUnprocessable();
    }
}
