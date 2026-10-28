<?php

namespace Tests\Feature;

use App\Domains\Intake\Services\ApplicationFields;
use App\Models\ApplicationRevision;
use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientApplication;
use App\Models\LoanApplication;
use App\Models\LoanParticipation;
use App\Models\Promoter;
use App\Models\Rate;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class IntakeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_session_returns_names_for_direct_and_role_permissions(): void
    {
        $user = $this->actor('Promotor');
        $direct = \Spatie\Permission\Models\Permission::findOrCreate('intake.access', 'web');
        $inherited = \Spatie\Permission\Models\Permission::findOrCreate('intake.submit', 'web');
        $user->givePermissionTo($direct);
        $user->roles->first()->givePermissionTo($inherited);
        $user->unsetRelation('roles');
        $response = $this->getJson('/api/me')->assertOk();
        $this->assertContains('intake.access', $response->json('permissions'));
        $this->assertContains('intake.submit', $response->json('permissions'));
        foreach ($response->json('permissions') as $permission) {
            $this->assertIsString($permission);
        }
        $response->assertJsonPath('roles.0.name', 'Promotor');
    }

    private function actor(string $role = 'Gerente', ?Branch $branch = null): User
    {
        $branch ??= Branch::create(['name' => 'Carrillo', 'city' => 'Carrillo']);
        $user = new User;
        $user->forceFill(['name' => 'Prueba', 'email' => Str::uuid().'@example.invalid', 'password' => 'test', 'branch_id' => $branch->id, 'is_active' => true])->save();
        Role::findOrCreate($role, 'web');
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }

    private function promoter(User $actor): Promoter
    {
        $user = $actor;
        if (! $actor->hasRole('Promotor')) {
            $user = new User;
            $user->forceFill(['name' => 'Promotor de prueba', 'email' => Str::uuid().'@example.invalid', 'password' => 'test', 'branch_id' => $actor->branch_id, 'is_active' => true])->save();
            Role::findOrCreate('Promotor', 'web');
            $user->assignRole('Promotor');
        }

        return Promoter::firstOrCreate(['id' => $user->id], ['branch_id' => $user->branch_id]);
    }

    private function team(User $user): Team
    {
        return Team::create(['name' => 'Mismo nombre', 'branch_id' => $user->branch_id, 'promoter_id' => $this->promoter($user)->id]);
    }

    private function curp(): string
    {
        $base = 'GODE900101HQRMRR0';
        $alphabet = preg_split('//u', '0123456789ABCDEFGHIJKLMNÑOPQRSTUVWXYZ', -1, PREG_SPLIT_NO_EMPTY);
        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            $sum += array_search($base[$i], $alphabet, true) * (18 - $i);
        }

        return $base.((10 - $sum % 10) % 10);
    }

    private function data(Team $team): array
    {
        return ['id' => (string) Str::uuid(), 'version' => 0, 'team_id' => $team->id, 'curp' => $this->curp(), 'confirm_existing' => false, 'submit' => false, 'fields' => ['pNombre' => 'Prueba', 'aPaterno' => 'Persona']];
    }

    public function test_document_versions_preserve_fields_and_signed_files_with_scoped_access(): void
    {
        Storage::fake('local');
        $actor = $this->actor();
        $team = $this->team($actor);
        $input = $this->data($team);
        $input['fields']['Observaciones'] = '<script>alert(1)</script>';
        $file = $this->postJson('/api/intake/client-files', $input)->assertOk()->json();
        $base = '/api/documents/sources/file/'.$file['id'];
        $id = $this->postJson($base, ['kind' => 'dossier'])->assertOk()->json('id');
        $this->postJson($base, ['kind' => 'dossier'])->assertOk()->assertJsonPath('id', $id);
        $this->postJson($base, ['kind' => 'installation'])->assertOk();
        $this->postJson($base, ['kind' => 'authorization'])->assertUnprocessable();
        $html = $this->getJson('/api/documents/versions/'.$id)->assertOk()->json('html');
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString($file['client']['folio'], $html);
        $this->assertSame(hash('sha256', $html), DB::table('document_versions')->where('id', $id)->value('sha256'));
        $record = ClientApplication::findOrFail($file['id']);
        $record->update(['version' => 2, 'fields' => ['pNombre' => 'Nombre cambiado', 'aPaterno' => 'Persona']]);
        $new = $this->postJson($base, ['kind' => 'dossier'])->assertOk()->json('id');
        $this->assertNotSame($id, $new);
        $this->assertSame($html, $this->getJson('/api/documents/versions/'.$id)->assertOk()->json('html'));
        $pdf = "%PDF-1.4\n1 0 obj<</Type /Catalog>>endobj\n%%EOF";
        $upload = fn () => UploadedFile::fake()->createWithContent('firmado.pdf', $pdf);
        $attachment = $this->post('/api/documents/versions/'.$id.'/signed', ['file' => $upload()], ['Accept' => 'application/json'])->assertOk()->json('id');
        $this->post('/api/documents/versions/'.$id.'/signed', ['file' => $upload()], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('id', $attachment);
        $this->assertDatabaseCount('document_signed_files', 1);
        $this->assertSame($pdf, $this->get('/api/documents/signed/'.$attachment)->assertOk()->getContent());
        $this->post('/api/documents/versions/'.$id.'/signed', ['file' => UploadedFile::fake()->createWithContent('no.pdf', '<script>bad</script>')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->actor('Gerente');
        $this->getJson($base)->assertNotFound();
        $this->getJson('/api/documents/versions/'.$id)->assertNotFound();
        $this->get('/api/documents/signed/'.$attachment)->assertNotFound();
        Sanctum::actingAs($actor);
        DB::table('document_versions')->where('id', $id)->update(['html' => 'altered']);
        $this->getJson('/api/documents/versions/'.$id)->assertConflict();
    }

    public function test_client_number_is_generated_at_save_and_never_taken_from_the_form(): void
    {
        $u = $this->actor();
        $team = $this->team($u);
        $data = $this->data($team);
        $data['fields']['ncliente'] = 'MANUAL-FALSO';
        $data['fields']['nCredito'] = 'CREDITO-FALSO';
        $saved = $this->postJson('/api/intake/client-files', $data)->assertOk()->json();
        $this->assertSame($saved['client']['folio'], $saved['fields']['ncliente']);
        $this->assertStringStartsWith('CLI-', $saved['fields']['ncliente']);
        $this->assertNull($saved['fields']['nCredito']);
        $data['client_id'] = $saved['client_id'];
        $data['client_version'] = $saved['client']['version'];
        $data['version'] = $saved['version'];
        $data['confirm_existing'] = true;
        unset($data['fields']['ncliente'], $data['fields']['nCredito']);
        $this->postJson('/api/intake/client-files', $data)->assertOk()->assertJsonPath('client.folio', $saved['client']['folio'])->assertJsonPath('fields.ncliente', $saved['client']['folio']);
    }

    public function test_folios_have_independent_branch_and_entity_sequences_and_are_not_reused(): void
    {
        $u = $this->actor();
        $branch = Branch::findOrFail($u->branch_id);
        $other = Branch::create(['name' => 'Otra', 'city' => 'Prueba']);
        $first = Client::create(['branch_id' => $branch->id, 'curp' => 'TEST1', 'first_name' => 'Uno', 'last_name' => 'Prueba']);
        $second = Client::create(['branch_id' => $branch->id, 'curp' => 'TEST2', 'first_name' => 'Dos', 'last_name' => 'Prueba']);
        $elsewhere = Client::create(['branch_id' => $other->id, 'curp' => 'TEST3', 'first_name' => 'Tres', 'last_name' => 'Prueba']);
        $this->assertSame('CLI-'.$branch->folio_code.'-000001', $first->folio);
        $this->assertSame('CLI-'.$branch->folio_code.'-000002', $second->folio);
        $this->assertSame('CLI-'.$other->folio_code.'-000001', $elsewhere->folio);
        $first->delete();
        $third = Client::create(['branch_id' => $branch->id, 'curp' => 'TEST4', 'first_name' => 'Cuatro', 'last_name' => 'Prueba']);
        $this->assertSame('CLI-'.$branch->folio_code.'-000003', $third->folio);
        $team = $this->team($u);
        $rate = new Rate;
        $rate->forceFill(['name' => 'Cobre', 'cicle' => 1, 'min_amount' => 1000, 'max_amount' => 20000, 'rate_factor' => 80])->save();
        $loan = LoanApplication::create(['branch_id' => $branch->id, 'team_id' => $team->id, 'promoter_id' => $u->id, 'rate_id' => $rate->id]);
        $this->assertSame('CRE-'.$branch->folio_code.'-000001', $loan->folio);
        $branch->update(['name' => 'Nombre nuevo']);
        $this->assertSame('CLI-'.$branch->fresh()->folio_code.'-000002', $second->fresh()->folio);
    }

    public function test_assigned_folio_and_origin_branch_cannot_be_overwritten(): void
    {
        $u = $this->actor();
        $client = Client::create(['branch_id' => $u->branch_id, 'curp' => 'TEST5', 'first_name' => 'Prueba', 'last_name' => 'Prueba', 'folio' => 'FORGED']);
        $this->assertStringStartsWith('CLI-', $client->folio);
        $client->folio = 'FORGED';
        $this->expectException(\LogicException::class);
        $client->save();
    }

    public function test_group_search_is_bounded_and_cannot_escape_users_branch(): void
    {
        $manager = $this->actor();
        $promoter = $this->promoter($manager);
        for ($i = 0; $i < 17; $i++) {
            Team::create(['name' => 'Las Flores '.$i, 'branch_id' => $manager->branch_id, 'promoter_id' => $promoter->id]);
        }
        $other = Branch::create(['name' => 'Otra sucursal', 'city' => 'Prueba']);
        $outsider = $this->actor('Gerente', $other);
        $outsideTeam = $this->team($outsider);
        $outsideTeam->update(['name' => 'Las Flores ajenas']);
        Sanctum::actingAs($manager);
        $result = $this->getJson('/api/intake/team-search?search=Flores')->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('has_more', true);
        foreach ($result->json('data') as $team) {
            $this->assertSame($manager->branch_id, $team['branch_id']);
        }
        $this->getJson('/api/intake/team-search?search=Flores&branch_id='.$other->id)->assertForbidden();
        $this->getJson('/api/intake/team-search?search=F')->assertUnprocessable();
        $this->getJson('/api/intake/team-search?search='.urlencode('%%'))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_global_group_search_requires_one_explicit_branch(): void
    {
        $admin = $this->actor('Super Admin');
        $team = $this->team($admin);
        $admin->branch_id = null;
        $admin->save();
        $this->getJson('/api/intake/team-search?search=Mismo')->assertUnprocessable();
        $this->getJson('/api/intake/team-search?search=Mismo&branch_id='.$team->branch_id)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $team->id);
    }

    public function test_same_group_name_is_allowed_with_different_uuids(): void
    {
        $u = $this->actor();
        $data = ['name' => 'Las flores', 'branch_id' => $u->branch_id, 'promoter_id' => $this->promoter($u)->id];
        $a = $this->postJson('/api/intake/teams', $data)->assertCreated()->assertJsonPath('created_by', $u->id)->json('id');
        $b = $this->postJson('/api/intake/teams', $data)->assertCreated()->json('id');
        $this->assertNotSame($a, $b);
    }

    public function test_invalid_curp_is_rejected_before_lookup(): void
    {
        $t = $this->team($this->actor());
        $this->postJson('/api/intake/clients/lookup', ['team_id' => $t->id, 'curp' => 'INVALIDA'])->assertUnprocessable();
        $bad = substr($this->curp(), 0, 17).(((int) substr($this->curp(), -1) + 1) % 10);
        $this->postJson('/api/intake/clients/lookup', ['team_id' => $t->id, 'curp' => $bad])->assertUnprocessable();
    }

    public function test_draft_survives_reload_and_edit_requires_explicit_confirmation(): void
    {
        $t = $this->team($this->actor());
        $data = $this->data($t);
        $saved = $this->postJson('/api/intake/applications', $data)->assertOk()->json();
        $lookup = $this->postJson('/api/intake/clients/lookup', ['team_id' => $t->id, 'curp' => strtolower($this->curp())])->assertOk()->assertJsonPath('found', true)->json();
        $this->assertSame($saved['id'], $lookup['application']['id']);
        $data['version'] = 1;
        $data['client_id'] = $saved['client_id'];
        $data['client_version'] = 1;
        $this->postJson('/api/intake/applications', $data)->assertConflict();
        $data['confirm_existing'] = true;
        $data['fields']['pNombre'] = 'Corregida';
        $this->postJson('/api/intake/applications', $data)->assertOk()->assertJsonPath('version', 2);
        $this->assertSame(1, Client::count());
        $this->assertSame(2, ApplicationRevision::count());
        $this->postJson('/api/intake/applications', $data)->assertConflict();
    }

    public function test_branch_scope_and_restricted_cross_branch_lookup(): void
    {
        $owner = $this->actor();
        $team = $this->team($owner);
        $saved = $this->postJson('/api/intake/applications', $this->data($team))->assertOk()->json();
        $other = $this->actor();
        $otherTeam = $this->team($other);
        $this->getJson('/api/intake/teams/'.$team->id)->assertNotFound();
        $this->postJson('/api/intake/clients/lookup', ['team_id' => $otherTeam->id, 'curp' => $this->curp()])->assertOk()->assertJsonPath('found', true)->assertJsonPath('can_edit', false)->assertJsonPath('profile', null)->assertJsonPath('client.branch', 'Carrillo');
        $data = $this->data($otherTeam);
        $data['client_id'] = $saved['client_id'];
        $data['confirm_existing'] = true;
        $data['client_version'] = 1;
        $this->postJson('/api/intake/applications', $data)->assertForbidden();
    }

    public function test_submit_requires_complete_form_and_does_not_change_existing_amount(): void
    {
        $t = $this->team($this->actor());
        $data = $this->data($t);
        $saved = $this->postJson('/api/intake/applications', $data)->assertOk()->json();
        $data['version'] = 1;
        $data['client_id'] = $saved['client_id'];
        $data['client_version'] = 1;
        $data['confirm_existing'] = true;
        $data['submit'] = true;
        $this->postJson('/api/intake/applications', $data)->assertUnprocessable();
        $this->assertNull(ClientApplication::first()->amount_requested);
    }

    public function test_cashier_and_inactive_users_cannot_enter_intake(): void
    {
        $this->actor('Caja');
        $this->getJson('/api/intake/teams')->assertForbidden();
        $u = $this->actor();
        $u->is_active = false;
        $u->save();
        $this->getJson('/api/intake/teams')->assertForbidden();
    }

    public function test_promoter_cannot_access_another_promoters_group(): void
    {
        $owner = $this->actor();
        $t = $this->team($owner);
        $branch = Branch::find($owner->branch_id);
        $this->actor('Promotor', $branch);
        $this->getJson('/api/intake/teams/'.$t->id)->assertNotFound();
    }

    public function test_options_work_even_if_not_all_roles_have_been_created(): void
    {
        $this->actor();
        $this->getJson('/api/intake/options')->assertOk()->assertJsonCount(1, 'branches');
    }

    public function test_complete_form_submits_and_preserves_72_fields_then_becomes_immutable(): void
    {
        $team = $this->team($this->actor());
        $data = $this->data($team);
        $fields = [];
        foreach (app(ApplicationFields::class)->schema() as $field) {
            $fields[$field['name']] = $field['options'] ? (string) $field['options'][0]['value'] : ($field['control'] === 'date' ? '2026-10-08' : ($field['control'] === 'number' ? '1' : 'Prueba'));
        }
        $fields['fNacimiento'] = '1990-01-01';
        foreach (['dudcp', 'rfcp', 'rpcp'] as $key) {
            $fields[$key] = '77000';
        }
        $data['fields'] = $fields;
        $data['submit'] = true;
        $saved = $this->postJson('/api/intake/applications', $data)->assertOk()->assertJsonPath('status', 'en_revision')->json();
        $this->assertCount(72, $saved['fields']);
        $this->assertSame($team->id, $saved['fields']['team_id']);
        $data['version'] = 1;
        $data['client_id'] = $saved['client_id'];
        $data['client_version'] = 1;
        $data['confirm_existing'] = true;
        $data['submit'] = false;
        $this->postJson('/api/intake/applications', $data)->assertConflict();
        $this->assertSame(1, ApplicationRevision::count());
    }

    public function test_client_file_rejects_financial_amounts(): void
    {
        $team = $this->team($this->actor());
        $data = $this->data($team);
        $saved = $this->postJson('/api/intake/applications', $data)->assertOk()->json();
        $data['version'] = 1;
        $data['client_id'] = $saved['client_id'];
        $data['client_version'] = 1;
        $data['confirm_existing'] = true;
        $data['amount_requested'] = '9000';
        $this->postJson('/api/intake/applications', $data)->assertUnprocessable();
        $this->assertNull(ClientApplication::first()->amount_requested);
        $this->assertSame(1, Client::first()->version);
    }

    public function test_lookup_reports_delivered_credit_and_does_not_treat_a_pending_application_as_a_loan(): void
    {
        $u = $this->actor();
        $team = $this->team($u);
        $saved = $this->postJson('/api/intake/applications', $this->data($team))->assertOk()->json();
        $rate = new Rate;
        $rate->forceFill(['name' => 'Cobre', 'cicle' => 1, 'min_amount' => 1000, 'max_amount' => 20000, 'rate_factor' => 80])->save();
        $loan = LoanApplication::create(['branch_id' => $u->branch_id, 'team_id' => $team->id, 'promoter_id' => $u->id, 'rate_id' => $rate->id, 'status' => 'entregado', 'pacted_date' => '2026-10-01']);
        LoanParticipation::create(['loan_application_id' => $loan->id, 'client_id' => $saved['client_id'], 'role_in_group' => 'integrante', 'amount_requested' => 10000]);
        $this->postJson('/api/intake/clients/lookup', ['team_id' => $team->id, 'curp' => $this->curp()])->assertOk()->assertJsonCount(1, 'active_credits')->assertJsonPath('last_credit.id', $loan->id)->assertJsonPath('last_credit.pacted_date', '2026-10-01');
        $loan->update(['status' => 'pagado']);
        $this->postJson('/api/intake/clients/lookup', ['team_id' => $team->id, 'curp' => $this->curp()])->assertJsonCount(0, 'active_credits')->assertJsonPath('last_credit.id', $loan->id);
    }

    public function test_committee_cannot_have_two_presidents(): void
    {
        $team = $this->team($this->actor());
        $a = Client::create(['branch_id' => $team->branch_id, 'curp' => $this->curp(), 'first_name' => 'Una', 'last_name' => 'Prueba']);
        $b = Client::create(['branch_id' => $team->branch_id, 'curp' => 'FIXTURE-SECOND-CLIENT', 'first_name' => 'Otra', 'last_name' => 'Prueba']);
        $one = TeamMembership::create(['branch_id' => $team->branch_id, 'team_id' => $team->id, 'client_id' => $a->id, 'role_in_group' => 'presidenta']);
        $two = TeamMembership::create(['branch_id' => $team->branch_id, 'team_id' => $team->id, 'client_id' => $b->id]);
        $this->patchJson('/api/intake/teams/'.$team->id.'/members/'.$two->id, ['role_in_group' => 'presidenta'])->assertUnprocessable();
        $this->patchJson('/api/intake/teams/'.$team->id.'/members/'.$two->id, ['role_in_group' => 'tesorera'])->assertOk();
        $this->assertSame('presidenta', $one->fresh()->role_in_group);
    }

    public function test_frontend_field_contract_matches_backend_and_legacy_inventory(): void
    {
        $backend = app(ApplicationFields::class)->schema();
        $frontend = json_decode(file_get_contents(base_path('../frontend/apps/admin-web/src/domain/intake-fields.json')), true);
        $legacy = json_decode(file_get_contents(base_path('../docs/migration/legacy-application-fields.json')), true);
        $this->assertSame($backend, $frontend);
        $this->assertSame(array_column($legacy['fields'], 'name'), array_column($backend, 'name'));
    }

    public function test_field_staff_can_prepare_a_draft_but_cannot_submit_it_as_manager(): void
    {
        $team = $this->team($this->actor('Promotor'));
        $data = $this->data($team);
        $this->postJson('/api/intake/applications', $data)->assertOk();
        $data['submit'] = true;
        $this->postJson('/api/intake/applications', $data)->assertForbidden();
    }

    public function test_super_admin_must_select_a_registered_promoter(): void
    {
        $admin = $this->actor('Super Admin');
        $this->postJson('/api/intake/teams', ['name' => 'Nuevo', 'branch_id' => $admin->branch_id])->assertUnprocessable();
        $this->postJson('/api/intake/teams', ['name' => 'Nuevo', 'branch_id' => $admin->branch_id, 'promoter_id' => $admin->id])->assertUnprocessable();
        $this->postJson('/api/intake/promoters', ['user_id' => $admin->id])->assertUnprocessable();
        $this->getJson('/api/intake/options')->assertOk()->assertJsonCount(0, 'promoters');
    }

    public function test_promoter_role_can_default_to_own_registered_uuid(): void
    {
        $actor = $this->actor('Promotor');
        $this->promoter($actor);
        $this->postJson('/api/intake/teams', ['name' => 'Propio', 'branch_id' => $actor->branch_id])->assertCreated()->assertJsonPath('promoter_id', $actor->id);
    }

    public function test_role_revocation_and_wrong_branch_prevent_assignment(): void
    {
        $admin = $this->actor('Super Admin');
        $promoter = $this->promoter($admin);
        $other = Branch::create(['name' => 'Otra', 'city' => 'Otra']);
        $data = ['name' => 'Grupo', 'branch_id' => $other->id, 'promoter_id' => $promoter->id];
        $this->postJson('/api/intake/teams', $data)->assertUnprocessable();
        $promoter->user->removeRole('Promotor');
        $data['branch_id'] = $admin->branch_id;
        $this->postJson('/api/intake/teams', $data)->assertUnprocessable();
        $this->getJson('/api/intake/options')->assertJsonCount(0, 'promoters');
    }

    public function test_admin_can_register_only_eligible_users_without_changing_roles(): void
    {
        $admin = $this->actor('Super Admin');
        $user = $this->actor('Promotor', Branch::find($admin->branch_id));
        Sanctum::actingAs($admin);
        $this->postJson('/api/intake/promoters', ['user_id' => $user->id])->assertCreated()->assertJsonPath('id', $user->id);
        $this->getJson('/api/intake/options')->assertJsonCount(1, 'promoters');
        $this->assertFalse($admin->hasRole('Promotor'));
    }

    public function test_migration_preserves_invalid_assignment_for_correction(): void
    {
        $migration = require database_path('migrations/2026_10_08_140000_separate_promoters.php');
        $migration->down();
        $admin = $this->actor('Super Admin');
        $id = (string) Str::uuid();
        DB::table('teams')->insert(['id' => $id, 'name' => 'Anterior', 'branch_id' => $admin->branch_id, 'promoter_id' => $admin->id, 'is_active' => true]);
        $migration->up();
        $team = Team::findOrFail($id);
        $this->assertNull($team->promoter_id);
        $this->assertSame($admin->id, $team->previous_promoter_user_id);
        $this->assertSame(0, Promoter::count());
    }

    public function test_non_promoter_roles_always_require_explicit_selection(): void
    {
        foreach (['Gerente', 'Gerente General', 'Coordinador', 'Super Admin'] as $role) {
            $actor = $this->actor($role);
            $promoter = $this->promoter($actor);
            $data = ['name' => 'Grupo de prueba', 'branch_id' => $actor->branch_id];
            $this->postJson('/api/intake/teams', $data)->assertUnprocessable()->assertJsonValidationErrors('promoter_id');
            $data['promoter_id'] = $promoter->id;
            $id = $this->postJson('/api/intake/teams', $data)->assertCreated()->json('id');
            $this->getJson('/api/intake/teams/'.$id)->assertOk();
        }
    }

    public function test_existing_capture_amount_is_preserved_but_not_exposed_or_required(): void
    {
        $team = $this->team($this->actor());
        $data = $this->data($team);
        $saved = $this->postJson('/api/intake/client-files', $data)->assertOk()->assertJsonMissingPath('amount_requested')->json();
        ClientApplication::first()->forceFill(['amount_requested' => '10000.00'])->save();
        $data['version'] = 1;
        $data['client_id'] = $saved['client_id'];
        $data['client_version'] = 1;
        $data['confirm_existing'] = true;
        $this->postJson('/api/intake/client-files', $data)->assertOk()->assertJsonMissingPath('amount_requested');
        $this->assertSame('10000.00', ClientApplication::first()->amount_requested);
    }
}
