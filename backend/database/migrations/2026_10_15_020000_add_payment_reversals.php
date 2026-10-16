<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_payment_reversals', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('credit_payment_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('actor_name');
            $t->text('reason');
            $t->decimal('amount', 12, 2);
            $t->string('method', 30);
            $t->date('reversed_on');
            $t->timestamp('created_at');
            $t->string('request_hash', 64);
            $t->json('snapshot');
            $t->index(['branch_id', 'reversed_on']);
        });
        Schema::create('active_bank_payment_references', function (Blueprint $t) {
            $t->string('deposit_key', 64)->primary();
            $t->foreignUuid('credit_payment_id')->unique()->constrained()->restrictOnDelete();
        });
        DB::table('credit_payments')->whereNotNull('deposit_key')->orderBy('id')->chunk(500, function ($rows) {
            foreach ($rows as $r) {
                DB::table('active_bank_payment_references')->insert(['deposit_key' => $r->deposit_key, 'credit_payment_id' => $r->id]);
            }
        });
        Schema::table('credit_payments', function (Blueprint $t) {
            $t->dropUnique(['deposit_key']);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('credit.reverse', 'web');
        foreach (['Super Admin', 'Gerente General', 'Gerente'] as $role) {
            Role::findOrCreate($role, 'web')->givePermissionTo(['credit.view', 'credit.reverse']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        throw new RuntimeException('Los reversos y referencias financieras requieren conservación auditada.');
    }
};
