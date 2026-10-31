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
        Schema::create('staff_push_devices', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('session_token_id');
            $t->string('credential_fingerprint', 64);
            $t->uuid('installation_id');
            $t->string('platform', 10);
            $t->string('token_hash', 64)->unique();
            $t->text('token_encrypted');
            $t->boolean('enabled')->default(true);
            $t->timestamps();
            $t->index(['user_id', 'installation_id']);
        });
        Schema::create('app_messages', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('recipient_type', 10);
            $t->uuid('recipient_id');
            $t->uuid('branch_id')->nullable();
            $t->uuid('sender_id')->nullable();
            $t->string('event_key', 64)->unique();
            $t->string('kind', 40);
            $t->string('title', 120);
            $t->text('body');
            $t->uuid('installment_id')->nullable();
            $t->uuid('payment_id')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamp('expires_at');
            $t->timestamps();
            $t->index(['recipient_type', 'recipient_id', 'created_at']);
        });
        Schema::create('app_message_deliveries', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('message_id')->constrained('app_messages')->restrictOnDelete();
            $t->uuid('device_id');
            $t->string('status', 30);
            $t->string('provider_reference')->nullable();
            $t->timestamps();
            $t->unique(['message_id', 'device_id']);
        });
        $p = Permission::firstOrCreate(['name' => 'messages.send', 'guard_name' => 'web']);
        foreach (['Super Admin', 'Gerente General', 'Gerente'] as $name) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($p);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        throw new RuntimeException('Messages and delivery audit must be retained.');
    }
};
