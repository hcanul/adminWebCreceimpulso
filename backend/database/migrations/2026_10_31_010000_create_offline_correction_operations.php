<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_correction_operations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $t->foreignUuid('application_id')->constrained('client_applications')->restrictOnDelete();
            $t->string('request_hash', 64);
            $t->unsignedInteger('application_version');
            $t->unsignedInteger('client_version');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Conservar auditoría de sincronización.');
    }
};
