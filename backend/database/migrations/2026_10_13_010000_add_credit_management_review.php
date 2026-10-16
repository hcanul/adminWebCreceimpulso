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
            $table->string('review_status', 40)->default('pending')->index();
            $table->unsignedInteger('review_version')->default(0);
            $table->json('review_checks')->nullable();
        });
        Schema::create('credit_review_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('loan_application_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_name');
            $table->unsignedInteger('version');
            $table->string('action', 40);
            $table->string('from_status', 40);
            $table->string('to_status', 40);
            $table->text('notes')->nullable();
            $table->json('checks');
            $table->json('files');
            $table->timestamp('created_at');
            $table->unique(['loan_application_id', 'version']);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('credit.review', 'web');
        Role::findOrCreate('Gestión', 'web')->givePermissionTo(['intake.access', 'credit.view', 'credit.review']);
        foreach (['Super Admin', 'Gerente General'] as $name) {
            Role::findOrCreate($name, 'web')->givePermissionTo('credit.review');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        throw new RuntimeException('El historial de revisión requiere una reversión planificada.');
    }
};
