<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rates', function (Blueprint $table) {
            // Null leaves historical percentage-based rates ineligible until explicitly configured.
            $table->string('calculation_method', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('min_amount', 10, 2)->nullable()->change();
            $table->decimal('max_amount', 10, 2)->nullable()->change();
        });
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->foreignUuid('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->json('request_snapshot')->nullable();
            $table->string('request_hash', 64)->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->enum('disbursement_method', ['cash_branch', 'bank_deposit'])->nullable();
            $table->enum('repayment_method', ['cash_branch', 'bank_deposit'])->nullable();
            $table->decimal('total_approved', 12, 2)->nullable()->default(null)->change();
        });
        Schema::table('loan_participations', function (Blueprint $table) {
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->decimal('amount_approved', 10, 2)->nullable()->default(null)->change();
            $table->unique(['loan_application_id', 'client_id']);
        });
        DB::table('loan_applications')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('loan_participations')->where('loan_application_id', $row->id)->update(['branch_id' => $row->branch_id]);
            }
        });
        foreach (['Cobre' => '80.0000', 'Bronce' => '75.0000', 'Plata' => '70.0000'] as $name => $factor) {
            DB::table('rates')->insert(['id' => (string) Str::uuid(), 'name' => $name, 'cicle' => 1, 'min_amount' => null, 'max_amount' => null, 'rate_factor' => $factor, 'calculation_method' => 'weekly_per_thousand', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['credit.view', 'credit.create', 'credit.rates'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        foreach (['Super Admin', 'Gerente General', 'Gerente', 'Coordinador', 'Promotor'] as $name) {
            $role = Role::findOrCreate($name, 'web');
            $role->givePermissionTo('credit.view');
            if (in_array($name, ['Super Admin', 'Gerente General', 'Gerente'])) {
                $role->givePermissionTo('credit.create');
            }
            if (in_array($name, ['Super Admin', 'Gerente General'])) {
                $role->givePermissionTo('credit.rates');
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        throw new RuntimeException('Conservar solicitudes y snapshots requiere una reversión planificada.');
    }
};
