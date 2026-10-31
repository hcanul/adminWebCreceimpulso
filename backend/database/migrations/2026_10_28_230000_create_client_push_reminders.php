<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_push_devices', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('client_account_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('session_token_id');
            $t->string('credential_fingerprint', 64);
            $t->uuid('installation_id');
            $t->string('platform', 10);
            $t->string('token_hash', 64)->unique();
            $t->text('token_encrypted');
            $t->boolean('enabled')->default(true);
            $t->timestamps();
            $t->index(['client_account_id', 'installation_id']);
        });
        Schema::create('client_payment_reminders', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('client_account_id')->constrained()->restrictOnDelete();
            $t->uuid('installation_id');
            $t->date('due_on');
            $t->string('status', 30);
            $t->string('provider_reference')->nullable();
            $t->timestamp('created_at');
            $t->timestamp('updated_at');
            $t->unique(['client_account_id', 'installation_id', 'due_on'], 'client_reminder_once');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_payment_reminders');
        Schema::dropIfExists('client_push_devices');
    }
};
