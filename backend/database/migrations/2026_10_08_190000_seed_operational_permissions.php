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
        Schema::create('access_audits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('actor_id')->index();
            $table->string('subject_type', 20);
            $table->string('subject_id', 36)->index();
            $table->string('action', 20);
            $table->json('changes');
            $table->timestamp('created_at');
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['intake.access', 'intake.submit', 'promoters.register'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        foreach (['Super Admin', 'Gerente General', 'Gerente', 'Coordinador', 'Promotor', 'Caja'] as $name) {
            $role = Role::findOrCreate($name, 'web');
            if ($name !== 'Caja') {
                $role->givePermissionTo('intake.access');
            }
            if (in_array($name, ['Super Admin', 'Gerente General', 'Gerente'])) {
                $role->givePermissionTo('intake.submit');
            }
            if (in_array($name, ['Super Admin', 'Gerente General'])) {
                $role->givePermissionTo('promoters.register');
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        throw new RuntimeException('La auditoría y los permisos requieren una reversión explícita para preservar cambios administrativos.');
    }
};
