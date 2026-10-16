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
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->string('authorization_status', 40)->default('awaiting_proposal')->index();
            $table->unsignedInteger('authorization_version')->default(0);
            $table->json('authorization_proposal')->nullable();
            $table->json('approved_snapshot')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
        });
        Schema::create('credit_authorization_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('loan_application_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_name');
            $table->unsignedInteger('version');
            $table->string('action', 40);
            $table->string('from_status', 40);
            $table->string('to_status', 40);
            $table->text('notes');
            $table->json('proposal');
            $table->timestamp('created_at');
            $table->unique(['loan_application_id', 'version']);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['credit.propose', 'credit.authorize'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        Role::findOrCreate('Coordinación Comercial', 'web')->givePermissionTo(['intake.access', 'credit.view', 'credit.authorize']);
        foreach (['Super Admin', 'Gerente General', 'Gerente'] as $name) {
            Role::findOrCreate($name, 'web')->givePermissionTo('credit.propose');
        }
        foreach (['Super Admin', 'Gerente General'] as $name) {
            Role::findOrCreate($name, 'web')->givePermissionTo('credit.authorize');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        throw new RuntimeException('Las autorizaciones financieras requieren una reversión planificada.');
    }
};
