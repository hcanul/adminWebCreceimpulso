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
        Schema::create('credit_disbursements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('loan_application_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_name');
            $table->date('disbursed_on');
            $table->date('first_due_on');
            $table->string('method', 30);
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_reference', 150)->nullable();
            $table->decimal('capital', 12, 2);
            $table->decimal('guarantee_retained', 12, 2);
            $table->decimal('net_delivered', 12, 2);
            $table->text('notes');
            $table->string('request_hash', 64);
            $table->json('snapshot');
            $table->timestamp('created_at');
        });
        Schema::create('loan_installments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('loan_application_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('loan_participation_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('client_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('number');
            $table->date('due_on');
            $table->decimal('amount_due', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->timestamp('created_at');
            $table->unique(['loan_participation_id', 'number']);
            $table->index(['branch_id', 'due_on']);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('credit.disburse', 'web');
        foreach (['Super Admin', 'Gerente General', 'Gerente', 'Caja'] as $name) {
            Role::findOrCreate($name, 'web')->givePermissionTo(['credit.view', 'credit.disburse']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        throw new RuntimeException('Los desembolsos y vencimientos requieren una reversión planificada.');
    }
};
