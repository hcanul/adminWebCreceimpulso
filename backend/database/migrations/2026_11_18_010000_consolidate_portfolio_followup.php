<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_applications', function (Blueprint $t) {
            $t->string('portfolio_class', 20)->default('ordinary')->index();
            $t->unsignedInteger('portfolio_version')->default(0);
        });
        Schema::create('portfolio_events', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('loan_application_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('actor_name');
            $t->string('previous_class', 20);
            $t->string('next_class', 20);
            $t->unsignedInteger('version');
            $t->text('reason');
            $t->string('request_hash', 64);
            $t->timestamp('created_at');
            $t->unique(['loan_application_id', 'version']);
        });
        Schema::create('credit_followup_entries', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('loan_application_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('loan_participation_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('client_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('actor_name');
            $t->unsignedInteger('sequence');
            $t->text('observation');
            $t->string('request_hash', 64)->nullable();
            $t->string('origin_source', 64)->nullable();
            $t->string('origin_id', 80)->nullable();
            $t->timestamp('created_at');
            $t->unique(['loan_application_id', 'sequence']);
            $t->unique(['origin_source', 'origin_id']);
            $t->index(['branch_id', 'created_at']);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['credit.followup', 'credit.portfolio.manage'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        foreach (['Super Admin', 'Gerente General'] as $role) {
            Role::findOrCreate($role, 'web')->givePermissionTo(['credit.view', 'credit.followup', 'credit.portfolio.manage']);
        }
        foreach (['Gerente', 'Cobranza'] as $role) {
            Role::findOrCreate($role, 'web')->givePermissionTo(['credit.view', 'credit.followup']);
        }
        Role::findOrCreate('Bancos', 'web')->givePermissionTo(['cash.view', 'cash.audit']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_followup_entries');
        Schema::dropIfExists('portfolio_events');
        Schema::table('loan_applications', fn (Blueprint $t) => $t->dropColumn(['portfolio_class', 'portfolio_version']));
        foreach (['credit.followup', 'credit.portfolio.manage'] as $permission) {
            Permission::where('name', $permission)->where('guard_name', 'web')->delete();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
