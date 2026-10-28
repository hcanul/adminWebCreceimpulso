<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_accounts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('client_id')->unique()->constrained()->restrictOnDelete();
            $t->string('password');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('client_activation_codes', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('client_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('issued_by')->constrained('users')->restrictOnDelete();
            $t->string('code_hash', 64)->unique();
            $t->timestamp('expires_at');
            $t->timestamp('consumed_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Los registros de activación requieren conservar su auditoría.');
    }
};
