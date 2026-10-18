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
        Schema::create('guarantee_applications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('loan_application_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('actor_name');
            $t->decimal('amount', 12, 2);
            $t->text('reason');
            $t->string('request_hash', 64);
            $t->date('applied_on');
            $t->timestamp('created_at');
        });
        Schema::create('guarantee_allocations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('guarantee_application_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('loan_installment_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('client_id')->constrained()->restrictOnDelete();
            $t->decimal('amount', 10, 2);
        });
        Schema::create('guarantee_reversals', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('guarantee_application_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('actor_name');
            $t->text('reason');
            $t->date('reversed_on');
            $t->timestamp('created_at');
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('guarantee.apply', 'web');
        foreach (['Super Admin', 'Coordinación Comercial'] as $name) {
            Role::findOrCreate($name, 'web')->givePermissionTo('guarantee.apply');
        }app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        throw new RuntimeException('Las aplicaciones de garantía son registros financieros y requieren reversión planificada.');
    }
};
