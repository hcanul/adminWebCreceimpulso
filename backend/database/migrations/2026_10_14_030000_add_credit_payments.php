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
        Schema::create('credit_payments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('loan_application_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('actor_name');
            $t->date('received_on');
            $t->string('method', 30);
            $t->string('bank_name', 100)->nullable();
            $t->string('bank_reference', 150)->nullable();
            $t->date('deposited_on')->nullable();
            $t->string('deposit_key', 64)->nullable()->unique();
            $t->decimal('amount', 12, 2);
            $t->unsignedInteger('number');
            $t->unique(['loan_application_id', 'number']);
            $t->text('notes')->nullable();
            $t->string('request_hash', 64);
            $t->timestamp('created_at');
            $t->index(['branch_id', 'received_on', 'method']);
        });
        Schema::create('credit_payment_allocations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('credit_payment_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('loan_installment_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('payer_client_id')->constrained('clients')->restrictOnDelete();
            $t->decimal('amount', 10, 2);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('credit.collect', 'web');
        foreach (['Super Admin', 'Gerente General', 'Caja'] as $name) {
            Role::findOrCreate($name, 'web')->givePermissionTo(['credit.view', 'credit.collect']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        throw new RuntimeException('Los cobros requieren una reversión auditada; no se eliminan automáticamente.');
    }
};
