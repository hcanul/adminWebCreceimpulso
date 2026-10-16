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
        Schema::create('cash_closings', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('folio', 40)->unique();
            $t->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $t->string('branch_name');
            $t->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('actor_name');
            $t->date('business_date');
            $t->decimal('cash_total', 16, 2);
            $t->decimal('bank_total', 16, 2);
            $t->decimal('cash_counted', 16, 2);
            $t->decimal('cash_difference', 16, 2);
            $t->unsignedInteger('payment_count');
            $t->text('notes')->nullable();
            $t->string('request_hash', 64);
            $t->timestamp('created_at');
            $t->index(['branch_id', 'business_date']);
        });
        Schema::create('cash_closing_payments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('cash_closing_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('credit_payment_id')->unique()->constrained()->restrictOnDelete();
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['cash.view', 'cash.close', 'cash.audit'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        foreach (['Super Admin', 'Gerente General'] as $name) {
            Role::findOrCreate($name, 'web')->givePermissionTo(['cash.view', 'cash.close', 'cash.audit']);
        }
        Role::findOrCreate('Caja', 'web')->givePermissionTo(['cash.view', 'cash.close']);
        Role::findOrCreate('Gerente', 'web')->givePermissionTo(['cash.view', 'cash.audit']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        throw new RuntimeException('Los cortes financieros requieren conservación y reversión auditada.');
    }
};
